<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Pdv\PdvCargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvCargaRespTecnicoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_carga_envia_responsavel_tecnico_do_erp(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA RESP TEC '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        VendasParametro::forEmpresa((int) $empresa->id)->forceFill([
            'resp_tecnico_id_csrt' => '01',
            'resp_tecnico_csrt' => 'token-csrt-teste',
        ])->save();

        $payload = app(PdvCargaService::class)->buildPull(null, (int) $empresa->id, null, 0, 10);
        $rt = $payload['empresa']['resp_tecnico'] ?? [];
        $fixed = NfeFiscalConfig::defaultRespTecnico();

        $this->assertSame($fixed['cnpj'], preg_replace('/\D/', '', (string) ($rt['cnpj'] ?? '')));
        $this->assertSame($fixed['contato'], (string) ($rt['contato'] ?? ''));
        $this->assertSame($fixed['email'], (string) ($rt['email'] ?? ''));
        $this->assertSame($fixed['fone'], preg_replace('/\D/', '', (string) ($rt['fone'] ?? '')));
        $this->assertSame('01', (string) ($rt['id_csrt'] ?? ''));
        $this->assertSame('token-csrt-teste', (string) ($rt['csrt'] ?? ''));
        $this->assertArrayNotHasKey('resp_tecnico_email', $payload['empresa']['params'] ?? []);
    }
}
