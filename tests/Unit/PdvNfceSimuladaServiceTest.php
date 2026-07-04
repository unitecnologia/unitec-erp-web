<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use Carbon\Carbon;
use Tests\TestCase;

class PdvNfceSimuladaServiceTest extends TestCase
{
    public function test_it_generates_valid_44_digit_access_key(): void
    {
        $service = new PdvNfceSimuladaService();
        $empresa = new Empresa([
            'uf' => 'SC',
            'cnpj' => '12.345.678/0001-90',
        ]);
        $venda = new PdvVenda([
            'id' => 42,
            'numero' => 7,
            'fechado_em' => Carbon::parse('2026-06-30 14:30:00'),
        ]);

        $chave = $service->gerarChaveAcesso($empresa, $venda, PdvFinalizarOperacao::NFCE_TRANSMITIR);

        $this->assertSame(44, strlen($chave));
        $this->assertMatchesRegularExpression('/^\d{44}$/', $chave);
        $this->assertSame($chave, $service->gerarChaveAcesso($empresa, $venda, PdvFinalizarOperacao::NFCE_TRANSMITIR));
    }

    public function test_contingencia_uses_offline_emission_type_in_key(): void
    {
        $service = new PdvNfceSimuladaService();
        $empresa = new Empresa(['uf' => 'SC', 'cnpj' => '12345678000190']);
        $venda = new PdvVenda([
            'id' => 10,
            'numero' => 3,
            'fechado_em' => Carbon::parse('2026-06-30 10:00:00'),
        ]);

        $normal = $service->gerarChaveAcesso($empresa, $venda, PdvFinalizarOperacao::NFCE_TRANSMITIR);
        $contingencia = $service->gerarChaveAcesso($empresa, $venda, PdvFinalizarOperacao::NFCE_CONTINGENCIA);

        $this->assertNotSame($normal, $contingencia);
        $this->assertSame('1', substr($normal, 34, 1));
        $this->assertSame('9', substr($contingencia, 34, 1));
    }

    public function test_build_view_data_marks_simulated_environment(): void
    {
        $service = new PdvNfceSimuladaService();
        $empresa = new Empresa([
            'fantasia' => 'Loja Teste',
            'razao_social' => 'Loja Teste LTDA',
            'cnpj' => '12345678000190',
            'uf' => 'SC',
            'cidade' => 'Florianópolis',
        ]);
        $venda = new PdvVenda([
            'id' => 5,
            'numero' => 1,
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'fiscal' => true,
            'nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
            'fechado_em' => Carbon::parse('2026-06-30 12:00:00'),
        ]);
        $venda->setRelation('itens', collect());
        $venda->setRelation('pagamentos', collect());

        $data = $service->buildViewData($venda, $empresa, 'Operador', PdvFinalizarOperacao::NFCE_TRANSMITIR);

        $this->assertStringContainsString('SIMULADO', $data['ambienteLabel']);
        $this->assertSame('AUTORIZADA (SIMULADO)', $data['statusLabel']);
        $this->assertNotEmpty($data['qrSvg']);
    }
}
