<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EntregasDevice;
use App\Models\User;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EntregasAuthApiTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_ping_publico(): void
    {
        $this->getJson('/api/v1/entregas/ping')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('app', 'unitec-entregas');
    }

    public function test_users_exige_device_aprovado(): void
    {
        $this->getJson('/api/v1/entregas/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'device_required');

        $device = EntregasDevice::query()->create([
            'device_uuid' => 'ent-pending-1',
            'status' => EntregasDevice::STATUS_PENDENTE,
            'registered_at' => now(),
        ]);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->getJson('/api/v1/entregas/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'device_not_approved');
    }

    public function test_login_me_logout_com_device_aprovado(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => '99',
            'nome' => 'EMPRESA ENTREGAS',
            'fantasia' => 'Entregas Teste',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'MOTORISTA TESTE',
            'senha_app_forca_vendas' => 'senha123',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);

        $device = EntregasDevice::query()->create([
            'device_uuid' => 'ent-ok-1',
            'status' => EntregasDevice::STATUS_APROVADO,
            'approved_at' => now(),
            'empresa_id' => $empresa->id,
        ]);

        $login = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->postJson('/api/v1/entregas/auth/login', [
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'senha' => 'senha123',
                'device_uuid' => $device->device_uuid,
                'device_name' => 'Celular Teste',
                'platform' => 'android',
                'app_version' => '1.0.0',
            ])
            ->assertOk();

        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);
        $login->assertJsonPath('user.name', 'MOTORISTA TESTE')
            ->assertJsonPath('user.empresa_id', $empresa->id);

        $this->withHeaders([
            'X-ENT-Device' => $device->device_uuid,
            'Authorization' => 'Bearer '.$token,
        ])
            ->getJson('/api/v1/entregas/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withHeaders([
            'X-ENT-Device' => $device->device_uuid,
            'Authorization' => 'Bearer '.$token,
        ])
            ->postJson('/api/v1/entregas/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'entregas:'.$device->device_uuid,
        ]);

        // Nova requisição sem usuário residual do guard (ciclo HTTP real reinicia o auth).
        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'X-ENT-Device' => $device->device_uuid,
            'Authorization' => 'Bearer '.$token,
        ])
            ->getJson('/api/v1/entregas/auth/me')
            ->assertUnauthorized();
    }

    public function test_login_negado_sem_acesso_app_entregas(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => '98',
            'nome' => 'EMPRESA SEM APP',
            'fantasia' => 'Sem App',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'SEM ACESSO ENTREGAS',
            'senha_app_forca_vendas' => 'senha123',
            'acesso_app_entregas' => false,
            'acesso_app_forca_vendas' => true,
            'ativo' => true,
        ]);

        $device = EntregasDevice::query()->create([
            'device_uuid' => 'ent-deny-1',
            'status' => EntregasDevice::STATUS_APROVADO,
            'approved_at' => now(),
            'empresa_id' => $empresa->id,
        ]);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->getJson('/api/v1/entregas/users?empresa_id='.$empresa->id)
            ->assertOk()
            ->assertJsonMissing(['id' => $user->id]);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->postJson('/api/v1/entregas/auth/login', [
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'senha' => 'senha123',
                'device_uuid' => $device->device_uuid,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['senha']);
    }

    public function test_register_device_retorna_pairing(): void
    {
        $resp = $this->postJson('/api/v1/entregas/devices/register', [
            'device_uuid' => 'ent-reg-1',
            'device_name' => 'Motorista Phone',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ])->assertOk();

        $this->assertNotEmpty($resp->json('pairing_code'));
        $this->assertDatabaseHas('entregas_devices', [
            'device_uuid' => 'ent-reg-1',
        ]);
    }
}
