<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpPermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_has_all_permissions(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'ativo' => true,
        ]);

        $this->assertTrue(ErpAccess::can($user, 'produtos.delete'));
        $this->assertTrue(ErpAccess::can($user, 'acesso.permissoes.manage'));
    }

    public function test_regular_user_only_has_assigned_permissions(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'ativo' => true,
        ]);

        $user->userPermissions()->create([
            'permission_key' => 'produtos.access',
        ]);

        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());

        $this->assertTrue(ErpAccess::can($user, 'produtos.access'));
        $this->assertFalse(ErpAccess::can($user, 'produtos.delete'));
    }

    public function test_inactive_user_is_denied(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'ativo' => false,
        ]);

        $this->assertFalse(ErpAccess::can($user, 'produtos.access'));
    }

    public function test_sync_user_permissions_persists_valid_keys_only(): void
    {
        $user = User::factory()->create(['ativo' => true]);

        ErpAccess::syncUserPermissions($user, [
            'produtos.print',
            'invalid.permission',
        ]);

        $user->refresh();

        $this->assertSame(['produtos.print'], $user->userPermissions()->pluck('permission_key')->all());
        $this->assertContains('produtos.print', ErpPermissionCatalog::allKeys());
    }
}
