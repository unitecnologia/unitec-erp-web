<?php

namespace App\Support\Erp;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ErpAccess
{
  public const SESSION_KEY = 'erp_effective_permissions';

  public static function can(?User $user, string $permission): bool
  {
    if (! $user || ! $user->ativo) {
      return false;
    }

    $empresa = ErpContext::currentEmpresa();

    if (
      EmpresaModulos::requerPrestadorServicosPorPermissao($permission)
      && ! EmpresaModulos::empresaPrestadorServicos($empresa)
    ) {
      return false;
    }

    // Administrador tem acesso total — módulos da empresa não podem esconder telas.
    if ($user->is_admin) {
      return true;
    }

    if (! EmpresaModulos::enabledForPermission($empresa, $permission)) {
      return false;
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

    try {
      app(\App\Support\Erp\License\LicencaRemotaService::class)->forgetLoginGate();
    } catch (\Throwable) {
      // ignore
    }
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
    if ($user->is_admin) {
      return;
    }

    $valid = array_values(array_unique(array_intersect($permissions, ErpPermissionCatalog::allKeys())));
    $now = now();
    $userId = $user->getKey();
    $rows = array_map(static fn (string $key): array => [
      'user_id' => $userId,
      'permission_key' => $key,
      'created_at' => $now,
      'updated_at' => $now,
    ], $valid);

    DB::transaction(static function () use ($userId, $rows): void {
      DB::table('user_permissions')->where('user_id', $userId)->delete();

      if ($rows !== []) {
        DB::table('user_permissions')->insert($rows);
      }
    });

    if ((int) Auth::id() === (int) $userId) {
      static::storeInSession($user, $valid);
    }
  }

  /**
   * @param  list<string>  $permissions
   */
  public static function syncProfilePermissions(\App\Models\ErpProfile $profile, array $permissions): void
  {
    $valid = array_values(array_unique(array_intersect($permissions, ErpPermissionCatalog::allKeys())));
    $now = now();
    $profileId = $profile->getKey();
    $rows = array_map(static fn (string $key): array => [
      'erp_profile_id' => $profileId,
      'permission_key' => $key,
      'created_at' => $now,
      'updated_at' => $now,
    ], $valid);

    DB::transaction(static function () use ($profileId, $rows): void {
      DB::table('erp_profile_permissions')->where('erp_profile_id', $profileId)->delete();

      if ($rows !== []) {
        DB::table('erp_profile_permissions')->insert($rows);
      }
    });
  }
}
