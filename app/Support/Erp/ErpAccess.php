<?php

namespace App\Support\Erp;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class ErpAccess
{
  public const SESSION_KEY = 'erp_effective_permissions';

  public static function can(?User $user, string $permission): bool
  {
    if (! $user || ! $user->ativo) {
      return false;
    }

    if ($user->is_admin) {
      return true;
    }

    return in_array($permission, static::permissionsFor($user), true);
  }

  public static function authorize(?User $user, string $permission): void
  {
    if (! static::can($user, $permission)) {
      throw new AuthorizationException('Sem permissão para: ' . ErpPermissionCatalog::labelForKey($permission));
    }
  }

  public static function authorizeOrNotify(?User $user, string $permission): bool
  {
    if (static::can($user, $permission)) {
      return true;
    }

    Notification::make()
      ->title('Acesso negado')
      ->body('Você não tem permissão para esta operação.')
      ->danger()
      ->send();

    return false;
  }

  /**
   * @return list<string>
   */
  public static function permissionsFor(User $user): array
  {
    if ($user->is_admin) {
      return ErpPermissionCatalog::allKeys();
    }

    $cached = session(static::SESSION_KEY);

    if (is_array($cached) && ($cached['user_id'] ?? null) === $user->getKey()) {
      return $cached['permissions'];
    }

    $permissions = $user->effectivePermissionKeys();
    static::storeInSession($user, $permissions);

    return $permissions;
  }

  /**
   * @param  list<string>  $permissions
   */
  public static function storeInSession(User $user, array $permissions): void
  {
    session([
      static::SESSION_KEY => [
        'user_id' => $user->getKey(),
        'permissions' => array_values(array_unique($permissions)),
      ],
    ]);
  }

  public static function forgetSession(): void
  {
    session()->forget(static::SESSION_KEY);
  }

  public static function currentCan(string $permission): bool
  {
    $user = Auth::user();

    return $user instanceof User && static::can($user, $permission);
  }

  /**
   * @param  list<string>  $permissions
   */
  public static function syncUserPermissions(User $user, array $permissions): void
  {
    $valid = array_values(array_intersect($permissions, ErpPermissionCatalog::allKeys()));

    $user->userPermissions()->delete();

    foreach ($valid as $permission) {
      $user->userPermissions()->create(['permission_key' => $permission]);
    }

    static::storeInSession($user->fresh() ?? $user, $valid);
  }

  /**
   * @param  list<string>  $permissions
   */
  public static function syncProfilePermissions(\App\Models\ErpProfile $profile, array $permissions): void
  {
    $valid = array_values(array_intersect($permissions, ErpPermissionCatalog::allKeys()));

    $profile->permissions()->delete();

    foreach ($valid as $permission) {
      $profile->permissions()->create(['permission_key' => $permission]);
    }
  }
}
