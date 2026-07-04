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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_supervisor' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->ativo;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function erpProfile(): BelongsTo
    {
        return $this->belongsTo(ErpProfile::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
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
