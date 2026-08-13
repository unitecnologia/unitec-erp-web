<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'password',
    'senha',
    'senha_app_forca_vendas',
    'empresa_id',
    'is_admin',
    'ativo',
    'erp_profile_id',
    'vendedor_id',
])]
#[Hidden(['password', 'remember_token', 'senha', 'senha_app_forca_vendas'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->ativo) {
            return false;
        }

        if ($panel->getId() !== 'gestor') {
            return true;
        }

        if ($this->is_admin) {
            return true;
        }

        return \App\Support\Erp\ErpAccess::can($this, 'produtos.access')
            || \App\Support\Erp\ErpAccess::can($this, 'ajusta_preco.access')
            || \App\Support\Erp\ErpAccess::can($this, 'ajuste_estoque.access');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user')->withTimestamps();
    }

    public function caixaContas(): BelongsToMany
    {
        return $this->belongsToMany(CaixaConta::class, 'caixa_conta_user')
            ->withPivot(['empresa_id', 'is_padrao'])
            ->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function accessibleEmpresaIds(): array
    {
        if ($this->is_admin) {
            return Empresa::query()
                ->where('ativo', true)
                ->orderBy('codigo')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $ids = $this->empresas()
            ->pluck('empresas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (filled($this->empresa_id)) {
            $ids[] = (int) $this->empresa_id;
        }

        return array_values(array_unique($ids));
    }

    public function canAccessEmpresa(int $empresaId): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return in_array($empresaId, $this->accessibleEmpresaIds(), true);
    }

    /**
     * Caixas liberados para o usuário na empresa.
     * Sem vínculo configurado: mantém compatibilidade e libera todos os operacionais.
     *
     * @return list<int>
     */
    public function accessibleCaixaContaIds(?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: (int) ($this->empresa_id ?? 0);

        $all = CaixaConta::query()
            ->assignable()
            ->orderBy('codigo')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($this->is_admin || $empresaId <= 0) {
            return $all;
        }

        $assigned = DB::table('caixa_conta_user')
            ->where('user_id', $this->getKey())
            ->where('empresa_id', $empresaId)
            ->orderByDesc('is_padrao')
            ->orderBy('caixa_conta_id')
            ->pluck('caixa_conta_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $assigned === [] ? $all : array_values(array_unique($assigned));
    }

    public function defaultCaixaContaId(?int $empresaId = null): ?int
    {
        $empresaId = $empresaId ?: (int) ($this->empresa_id ?? 0);

        if ($empresaId <= 0) {
            return null;
        }

        $padrao = DB::table('caixa_conta_user')
            ->where('user_id', $this->getKey())
            ->where('empresa_id', $empresaId)
            ->where('is_padrao', true)
            ->value('caixa_conta_id');

        if ($padrao) {
            return (int) $padrao;
        }

        $ids = $this->accessibleCaixaContaIds($empresaId);

        return $ids[0] ?? null;
    }

    public function defaultCaixaContaNome(?int $empresaId = null): ?string
    {
        $id = $this->defaultCaixaContaId($empresaId);

        if (! $id) {
            return null;
        }

        $nome = trim((string) (CaixaConta::query()->whereKey($id)->value('nome') ?? ''));

        return $nome !== '' ? $nome : null;
    }

    public function canAccessCaixaConta(int $caixaContaId, ?int $empresaId = null): bool
    {
        return in_array($caixaContaId, $this->accessibleCaixaContaIds($empresaId), true);
    }

    public function erpProfile(): BelongsTo
    {
        return $this->belongsTo(ErpProfile::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    /**
     * Admin e usuário sem colaborador vinculado: sem restrição de PDV.
     * Colaborador sem PDVs marcados: sem restrição.
     * Colaborador com PDVs marcados: só esses terminais.
     */
    public function podeOperarPdvNoTerminal(?Terminal $terminal): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if (! $terminal?->id) {
            return true;
        }

        $vendedor = $this->relationLoaded('vendedor')
            ? $this->vendedor
            : $this->vendedor()->with('terminais')->first();

        if (! $vendedor) {
            return true;
        }

        if (! $vendedor->relationLoaded('terminais')) {
            $vendedor->load('terminais');
        }

        return $vendedor->podeUsarTerminal((int) $terminal->id);
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

  /**
   * @return list<string>
   */
  public function effectivePermissionKeys(): array
  {
    if ($this->is_admin) {
      return \App\Support\Erp\ErpPermissionCatalog::allKeys();
    }

    $keys = $this->userPermissions()
      ->pluck('permission_key')
      ->all();

    if ($this->erp_profile_id) {
      $profileKeys = ErpProfilePermission::query()
        ->where('erp_profile_id', $this->erp_profile_id)
        ->pluck('permission_key')
        ->all();

      $keys = array_merge($keys, $profileKeys);
    }

    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
  }

  public function erpCan(string $permission): bool
  {
    return \App\Support\Erp\ErpAccess::can($this, $permission);
  }
}
