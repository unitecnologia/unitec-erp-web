<?php

declare(strict_types=1);

namespace Tests\Unit\Boleto;

use App\Models\BoletoContaApi;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Tests\TestCase;

final class BoletoContaApiOverlayTest extends TestCase
{
    public function test_overlay_aplica_credenciais_da_conta(): void
    {
        $empresa = new Empresa([
            'param_boleto_habilitar' => false,
            'param_boleto_banco' => '001',
            'param_boleto_agencia' => '0000',
        ]);
        $empresa->id = 10;

        $conta = new BoletoContaApi([
            'empresa_id' => 10,
            'banco' => EmpresaParametros::BOLETO_BANCO_SICREDI,
            'ambiente' => 'homologacao',
            'agencia' => '6789',
            'agencia_dv' => '03',
            'beneficiario_codigo' => '12345',
            'dev_app_key' => 'key-x',
            'senha_api' => 'senha',
            'pos_vencimento' => 'protesto',
            'protesto_dias' => '10',
            'pix_hibrido' => true,
            'ativo' => true,
            'padrao' => true,
        ]);

        $overlay = $conta->asEmpresaOverlay($empresa);

        self::assertSame(10, $overlay->id);
        self::assertSame(EmpresaParametros::BOLETO_BANCO_SICREDI, $overlay->param_boleto_banco);
        self::assertSame('6789', $overlay->param_boleto_agencia);
        self::assertSame('03', $overlay->param_boleto_agencia_dv);
        self::assertSame('12345', $overlay->param_boleto_beneficiario_codigo);
        self::assertTrue((bool) $overlay->param_boleto_habilitar);
        self::assertTrue((bool) $overlay->param_boleto_pix_hibrido);
        self::assertSame('protesto', $overlay->param_boleto_pos_vencimento);
        self::assertSame('Ailos — Matriz', (new BoletoContaApi([
            'banco' => '085',
            'nome' => 'Matriz',
        ]))->rotulo());
    }
}
