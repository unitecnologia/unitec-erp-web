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

    public function test_carga_envia_responsavel_tecnico_padrao_quando_vazio(): void
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

    public function test_carga_respeita_responsavel_tecnico_customizado(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA RESP TEC CUSTOM '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        VendasParametro::forEmpresa((int) $empresa->id)->forceFill([
            'resp_tecnico_cnpj' => '11222333000181',
            'resp_tecnico_contato' => 'contato custom',
            'resp_tecnico_email' => 'custom@exemplo.com',
            'resp_tecnico_fone' => '47999998888',
            'resp_tecnico_id_csrt' => '02',
            'resp_tecnico_csrt' => 'token-custom',
        ])->save();

        $payload = app(PdvCargaService::class)->buildPull(null, (int) $empresa->id, null, 0, 10);
        $rt = $payload['empresa']['resp_tecnico'] ?? [];

        $this->assertSame('11222333000181', preg_replace('/\D/', '', (string) ($rt['cnpj'] ?? '')));
        $this->assertSame('contato custom', (string) ($rt['contato'] ?? ''));
        $this->assertSame('custom@exemplo.com', (string) ($rt['email'] ?? ''));
        $this->assertSame('47999998888', preg_replace('/\D/', '', (string) ($rt['fone'] ?? '')));
        $this->assertSame('02', (string) ($rt['id_csrt'] ?? ''));
        $this->assertSame('token-custom', (string) ($rt['csrt'] ?? ''));
    }
}
