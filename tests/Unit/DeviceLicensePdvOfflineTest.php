<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeviceLicensePdvOfflineTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): DeviceLicenseService
    {
        return app(DeviceLicenseService::class);
    }

    private function skipWithoutDeviceColumns(): void
    {
        if (! Schema::hasColumn('terminais', 'device_uuid')) {
            $this->markTestSkipped('Colunas de dispositivo ausentes em terminais.');
        }
    }

    public function test_register_recreates_pdv_after_delete_with_new_id(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV OFFLINE',
            'ativo' => true,
        ]);

        $uuid = 'pdv-device-'.uniqid('', true);

        $first = $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.52', $uuid);
        $firstId = (int) $first->id;

        $this->service()->releasePdvOfflineTerminalBeforeDelete($first);
        $first->delete();

        $second = $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.52', $uuid);

        $this->assertNotSame($firstId, (int) $second->id);
        $this->assertSame('PDV1', strtoupper(trim($second->nome)));
        $this->assertSame(1, (int) $second->numero_logico_terminal);
        $this->assertSame($uuid, $second->device_uuid);
    }

    public function test_register_blocks_different_device_when_pdv_already_bound(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV BLOQUEIO',
            'ativo' => true,
        ]);

        $existing = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => 'uuid-aparelho-a',
            'origens_dispositivo' => ['pdv_offline'],
            'device_last_seen_at' => now(),
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        try {
            $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.99', 'uuid-aparelho-b');
            $this->fail('Esperava DeviceLicenseLimitExceeded.');
        } catch (DeviceLicenseLimitExceeded $e) {
            $this->assertStringContainsString('já está em uso', $e->getMessage());
            $this->assertStringContainsString('F4', $e->getMessage());
        }

        $this->assertSame(
            (int) $existing->id,
            (int) Terminal::query()
                ->where('empresa_id', $empresa->id)
                ->where('nome', 'PDV1')
                ->value('id'),
        );
    }

    public function test_register_same_device_accepts_uuid_case_difference(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV CASE',
            'ativo' => true,
        ]);

        $existing = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => 'AEACE488-E29D-4773-8436-D85106D857A0',
            'origens_dispositivo' => ['pdv_offline'],
            'device_last_seen_at' => now(),
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        $terminal = $this->service()->registerPdvOffline(
            $empresa->id,
            '1',
            '192.168.0.53',
            'aeace488-e29d-4773-8436-d85106d857a0',
        );

        $this->assertSame((int) $existing->id, (int) $terminal->id);
    }

    public function test_register_existing_pdv_accepts_empty_incoming_uuid(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV UUID VAZIO',
            'ativo' => true,
        ]);

        $existing = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => 'uuid-aparelho-a',
            'origens_dispositivo' => ['pdv_offline'],
            'device_last_seen_at' => now(),
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        $terminal = $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.53', '');

        $this->assertSame((int) $existing->id, (int) $terminal->id);
        $this->assertSame('uuid-aparelho-a', $terminal->device_uuid);
    }

    public function test_register_binds_pre_cadastro_terminal_without_pdv_offline_origin(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV PRE CADASTRO',
            'ativo' => true,
        ]);

        Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => 'uuid-stale-erp',
            'origens_dispositivo' => ['erp_web'],
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        $terminal = $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.52', 'uuid-pdv-real');

        $this->assertSame('uuid-pdv-real', $terminal->device_uuid);
        $this->assertContains('pdv_offline', $terminal->origens_dispositivo ?? []);
    }

    public function test_release_clears_uuid_from_other_terminals_on_delete(): void
    {
        $this->skipWithoutDeviceColumns();

        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PDV RELEASE',
            'ativo' => true,
        ]);

        $uuid = 'shared-uuid-'.uniqid('', true);

        $pdv = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'device_uuid' => $uuid,
            'origens_dispositivo' => ['pdv_offline'],
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        $erp = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'ERP1',
            'pdv' => false,
            'ativo' => true,
            'device_uuid' => null,
            'origens_dispositivo' => ['erp_web'],
            'categoria_licenca' => DeviceLicenseService::CATEGORY_COMPUTADOR,
        ]);

        $this->service()->releasePdvOfflineTerminalBeforeDelete($pdv);
        $pdv->delete();

        $this->assertNull($erp->fresh()->device_uuid);

        $recreated = $this->service()->registerPdvOffline($empresa->id, '1', '192.168.0.52', $uuid);
        $this->assertSame($uuid, $recreated->device_uuid);
        $this->assertSame('PDV1', strtoupper(trim($recreated->nome)));
    }
}
