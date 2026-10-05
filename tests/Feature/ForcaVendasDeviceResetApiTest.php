<?php

namespace Tests\Feature;

use App\Models\ForcaVendasDevice;
use App\Models\ForcaVendasDeviceReset;
use App\Models\User;
use App\Support\ForcaVendas\ForcaVendasDeviceResetService;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasDeviceResetApiTest extends TestCase
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

    public function test_sem_autorizacao_nao_ha_reset_pendente(): void
    {
        $device = $this->device();

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);
    }

    public function test_reset_autorizado_e_concluido_uma_unica_vez(): void
    {
        $device = $this->device();
        $admin = User::factory()->create();

        $result = app(ForcaVendasDeviceResetService::class)->autorizar($device, $admin);
        $reset = $result['reset'];

        $this->assertTrue($result['criado']);
        $this->assertSame($admin->id, $reset->authorized_by);
        $this->assertNotNull($reset->authorized_at);
        $this->assertSame(ForcaVendasDeviceReset::STATUS_PENDENTE, $reset->status);

        $again = app(ForcaVendasDeviceResetService::class)->autorizar($device, $admin);
        $this->assertFalse($again['criado']);
        $this->assertSame($reset->uuid, $again['reset']->uuid);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('reset_uuid', $reset->uuid);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->postJson("/api/v1/forca-vendas/devices/reset/{$reset->uuid}/concluir", ['app_version' => '1.0.0'])
            ->assertOk()
            ->assertJsonPath('ja_concluido', false);

        $reset->refresh();
        $this->assertSame(ForcaVendasDeviceReset::STATUS_CONCLUIDO, $reset->status);
        $this->assertNotNull($reset->completed_at);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->getJson('/api/v1/forca-vendas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);

        $this->withHeader('X-FV-Device', $device->device_uuid)
            ->postJson("/api/v1/forca-vendas/devices/reset/{$reset->uuid}/concluir")
            ->assertOk()
            ->assertJsonPath('ja_concluido', true);

        $this->assertTrue($device->fresh()->isApproved());
    }

    public function test_outro_aparelho_nao_ve_nem_conclui_o_reset(): void
    {
        $alvo = $this->device('device-a');
        $outro = $this->device('device-b');
        $reset = app(ForcaVendasDeviceResetService::class)->autorizar($alvo, null)['reset'];

        $this->withHeader('X-FV-Device', $outro->device_uuid)
            ->getJson('/api/v1/forca-vendas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);

        $this->withHeader('X-FV-Device', $outro->device_uuid)
            ->postJson("/api/v1/forca-vendas/devices/reset/{$reset->uuid}/concluir")
            ->assertNotFound();

        $this->assertSame(ForcaVendasDeviceReset::STATUS_PENDENTE, $reset->fresh()->status);
    }
}
