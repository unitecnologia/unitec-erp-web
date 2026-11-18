<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendasInternasDevice;
use App\Models\VendasInternasDeviceReset;
use App\Support\VendasInternas\VendasInternasDeviceResetService;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class VendasInternasDeviceResetApiTest extends TestCase
{
    use MigratesSqliteMemory;

    private function device(string $uuid = 'vi-device-a'): VendasInternasDevice
    {
        return VendasInternasDevice::query()->create([
            'device_uuid' => $uuid,
            'device_name' => 'Teste VI',
            'status' => VendasInternasDevice::STATUS_APROVADO,
        ]);
    }

    public function test_sem_autorizacao_nao_ha_reset_pendente(): void
    {
        $device = $this->device();

        $this->withHeader('X-VI-Device', $device->device_uuid)
            ->getJson('/api/v1/vendas-internas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);
    }

    public function test_reset_autorizado_e_concluido_uma_unica_vez(): void
    {
        $device = $this->device();
        $admin = User::factory()->create();

        $result = app(VendasInternasDeviceResetService::class)->autorizar($device, $admin);
        $reset = $result['reset'];

        $this->assertTrue($result['criado']);
        $this->assertSame($admin->id, $reset->authorized_by);
        $this->assertNotNull($reset->authorized_at);
        $this->assertSame(VendasInternasDeviceReset::STATUS_PENDENTE, $reset->status);

        $again = app(VendasInternasDeviceResetService::class)->autorizar($device, $admin);
        $this->assertFalse($again['criado']);
        $this->assertSame($reset->uuid, $again['reset']->uuid);

        $this->withHeader('X-VI-Device', $device->device_uuid)
            ->getJson('/api/v1/vendas-internas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('reset_uuid', $reset->uuid);

        $device->forceFill(['user_id' => $admin->id, 'current_token_id' => 99])->save();

        $this->withHeader('X-VI-Device', $device->device_uuid)
            ->postJson("/api/v1/vendas-internas/devices/reset/{$reset->uuid}/concluir", ['app_version' => '1.2.7'])
            ->assertOk()
            ->assertJsonPath('ja_concluido', false);

        $reset->refresh();
        $this->assertSame(VendasInternasDeviceReset::STATUS_CONCLUIDO, $reset->status);
        $this->assertNotNull($reset->completed_at);

        $fresh = $device->fresh();
        $this->assertNull($fresh->user_id);
        $this->assertNull($fresh->current_token_id);
        $this->assertTrue($fresh->isApproved());

        $this->withHeader('X-VI-Device', $device->device_uuid)
            ->getJson('/api/v1/vendas-internas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);

        $this->withHeader('X-VI-Device', $device->device_uuid)
            ->postJson("/api/v1/vendas-internas/devices/reset/{$reset->uuid}/concluir")
            ->assertOk()
            ->assertJsonPath('ja_concluido', true);
    }

    public function test_outro_aparelho_nao_ve_nem_conclui_o_reset(): void
    {
        $alvo = $this->device('vi-device-a');
        $outro = $this->device('vi-device-b');
        $reset = app(VendasInternasDeviceResetService::class)->autorizar($alvo, null)['reset'];

        $this->withHeader('X-VI-Device', $outro->device_uuid)
            ->getJson('/api/v1/vendas-internas/devices/reset')
            ->assertOk()
            ->assertJsonPath('pending', false);

        $this->withHeader('X-VI-Device', $outro->device_uuid)
            ->postJson("/api/v1/vendas-internas/devices/reset/{$reset->uuid}/concluir")
            ->assertNotFound();

        $this->assertSame(VendasInternasDeviceReset::STATUS_PENDENTE, $reset->fresh()->status);
    }
}
