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
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'password',
    'senha',
    'senha_app_forca_vendas',
    'empresa_id',
    'is_admin',
    'is_supervisor',
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
            'is_supervisor' => 'boolean',
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
