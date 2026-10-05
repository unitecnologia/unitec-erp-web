<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ForcaVendasDevice;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\ForcaVendas\ForcaVendasDeviceResetService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasDeviceVinculoTest extends TestCase
{
    use MigratesSqliteMemory;

    private function device(string $uuid = 'device-a'): ForcaVendasDevice
    {
        return ForcaVendasDevice::query()->create([
            'device_uuid' => $uuid,
            'device_name' => 'Teste',
            'status' => ForcaVendasDevice::STATUS_APROVADO,
        ]);
    }

    private ?int $empresaId = null;

    private function empresaId(): int
    {
        return $this->empresaId ??= (int) Empresa::query()->forceCreate([
            'codigo' => '1',
            'nome' => 'EMPRESA',
            'razao_social' => 'EMPRESA LTDA',
            'ativo' => true,
        ])->id;
    }

    private function vendedorUser(string $nome, bool $comVendedor = true, bool $comEmpresa = false): User
    {
        $vendedorId = null;

        if ($comVendedor) {
            $vendedorId = Vendedor::query()->forceCreate([
                'codigo' => (string) (Vendedor::query()->count() + 1),
                'nome' => $nome,
                'ativo' => true,
            ])->id;
        }

        return User::factory()->create([
            'empresa_id' => $comEmpresa ? $this->empresaId() : null,
            'name' => $nome,
            'ativo' => true,
            'vendedor_id' => $vendedorId,
            'acesso_app_forca_vendas' => true,
            'senha_app_forca_vendas' => '1234',
        ]);
    }

    private function login(ForcaVendasDevice $device, User $user, string $senha = '1234')
    {
        return $this->withHeader('X-FV-Device', $device->device_uuid)
            ->postJson('/api/v1/forca-vendas/auth/login', [
                'user_id' => $user->id,
                'senha' => $senha,
                'device_uuid' => $device->device_uuid,
            ]);
    }

    public function test_primeiro_login_vincula_e_outro_vendedor_e_recusado(): void
    {
        $device = $this->device();
        $joao = $this->vendedorUser('JOAO');
        $marcos = $this->vendedorUser('MARCOS');

        $this->login($device, $joao)
            ->assertOk()
            ->assertJsonPath('device.vinculo_user_id', $joao->id);
        $this->assertSame($joao->id, $device->fresh()->user_id);

        $this->login($device, $marcos)
            ->assertForbidden()
            ->assertJsonPath('code', 'device_vinculado_outro')
            ->assertJsonPath('message', 'Este aparelho está vinculado a outro vendedor.')
            ->assertJsonMissingPath('token');
        $this->assertSame($joao->id, $device->fresh()->user_id);
        $this->assertSame(0, $marcos->tokens()->count());

        $this->login($device, $joao)->assertOk();
        $this->assertSame($joao->id, $device->fresh()->user_id);
    }

    public function test_lista_de_usuarios_mostra_so_o_vinculado(): void
    {
        $device = $this->device();
        $joao = $this->vendedorUser('JOAO', comEmpresa: true);
        $this->vendedorUser('MARCOS', comEmpresa: true);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/users?empresa_id='.$this->empresaId())
            ->assertOk()
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('vinculo_user_id', null);

        $device->forceFill(['user_id' => $joao->id])->save();

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/users?empresa_id='.$this->empresaId())
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $joao->id)
            ->assertJsonPath('vinculo_user_id', $joao->id);
    }

    public function test_aparelho_livre_lista_so_vendedores_ativos_habilitados_da_empresa(): void
    {
        $device = $this->device();
        $ok = $this->vendedorUser('JOAO', comEmpresa: true);
        $semVendedor = $this->vendedorUser('ADMIN', false, true);
        $semFlag = $this->vendedorUser('CAIXA', comEmpresa: true);
        $semFlag->forceFill(['acesso_app_forca_vendas' => false])->save();
        $inativo = $this->vendedorUser('INATIVO', comEmpresa: true);
        $inativo->forceFill(['ativo' => false])->save();
        $vendedorInativo = $this->vendedorUser('VEND INATIVO', comEmpresa: true);
        Vendedor::query()->whereKey($vendedorInativo->vendedor_id)->update(['ativo' => false]);
        $outraEmpresaId = (int) Empresa::query()->forceCreate([
            'codigo' => '2',
            'nome' => 'OUTRA',
            'razao_social' => 'OUTRA LTDA',
            'ativo' => true,
        ])->id;
        $outraEmpresa = $this->vendedorUser('OUTRA');
        $outraEmpresa->forceFill(['empresa_id' => $outraEmpresaId])->save();

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/users?empresa_id='.(int) $ok->empresa_id)
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $ok->id);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/users')
            ->assertOk()
            ->assertJsonCount(0, 'users');

        $this->assertNotNull($semVendedor->id);
    }

    public function test_usuario_sem_vendedor_nao_vincula(): void
    {
        $device = $this->device();
        $semVendedor = $this->vendedorUser('ADMIN', false);

        $this->login($device, $semVendedor)
            ->assertForbidden()
            ->assertJsonPath('code', 'user_sem_vendedor')
            ->assertJsonPath('message', 'Usuário sem vendedor vinculado no ERP.');
        $this->assertNull($device->fresh()->user_id);
    }

    public function test_logout_mantem_vinculo(): void
    {
        $device = $this->device();
        $joao = $this->vendedorUser('JOAO');
        $token = $this->login($device, $joao)->json('token');

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->withToken($token)
            ->postJson('/api/v1/forca-vendas/auth/logout')
            ->assertOk();

        $this->assertSame($joao->id, $device->fresh()->user_id);
    }

    public function test_token_de_outro_usuario_nao_acessa_sync(): void
    {
        $device = $this->device();
        $joao = $this->vendedorUser('JOAO');
        $marcos = $this->vendedorUser('MARCOS');
        $semVendedor = $this->vendedorUser('ADMIN', false);
        $device->forceFill(['user_id' => $joao->id])->save();

        Sanctum::actingAs($marcos, ['forca-vendas']);
        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/sync/pull')
            ->assertForbidden()
            ->assertJsonPath('code', 'device_vinculado_outro');

        Sanctum::actingAs($semVendedor, ['forca-vendas']);
        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/sync/pull')
            ->assertForbidden()
            ->assertJsonPath('code', 'user_sem_vendedor');

        Sanctum::actingAs($joao, ['forca-vendas']);
        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/auth/me')
            ->assertOk();
    }

    public function test_reset_concluido_libera_aparelho_para_outro_vendedor(): void
    {
        $device = $this->device();
        $joao = $this->vendedorUser('JOAO');
        $marcos = $this->vendedorUser('MARCOS');

        $this->login($device, $joao)->assertOk();
        $this->login($device, $marcos)->assertForbidden();

        $reset = app(ForcaVendasDeviceResetService::class)->autorizar($device, null)['reset'];

        // Reset só autorizado ainda não libera.
        $this->login($device, $marcos)->assertForbidden();

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->postJson("/api/v1/forca-vendas/devices/reset/{$reset->uuid}/concluir")
            ->assertOk();

        $device->refresh();
        $this->assertNull($device->user_id);
        $this->assertTrue($device->isApproved());
        $this->assertSame(0, $joao->tokens()->count());

        $this->login($device, $marcos)
            ->assertOk()
            ->assertJsonPath('device.vinculo_user_id', $marcos->id);
        $this->assertSame($marcos->id, $device->fresh()->user_id);

        $this->login($device, $joao)->assertForbidden();
    }
}
