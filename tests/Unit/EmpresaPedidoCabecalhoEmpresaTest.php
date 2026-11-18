<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sem MigratesSqliteMemory: parâmetro usa json_fallback (sem coluna/migration).
 */
class EmpresaPedidoCabecalhoEmpresaTest extends TestCase
{
    public function test_parametro_no_catalogo_default_false_grupo_geral_json_fallback(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = EmpresaParametros::COLUMN_PEDIDO_CABECALHO_EMPRESA;

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertTrue($fields[$coluna]['json_fallback']);
        $this->assertSame(
            'Exibir dados da empresa no cabeçalho do pedido de venda',
            $fields[$coluna]['label'],
        );
        $this->assertSame('geral', EmpresaParametros::permissionGroupForField($coluna));
        $this->assertContains($coluna, EmpresaParametros::permissionFieldsJsonFallback());
    }

    public function test_gravacao_e_leitura_por_empresa_via_json_independente(): void
    {
        Storage::fake('local');

        $empresaA = 910011;
        $empresaB = 910012;

        $this->assertFalse(EmpresaParametros::pedidoExibirCabecalhoEmpresa($empresaA));
        $this->assertFalse(EmpresaParametros::pedidoExibirCabecalhoEmpresa($empresaB));

        EmpresaParametros::gravarPedidoExibirCabecalhoEmpresa($empresaA, true);

        $this->assertTrue(EmpresaParametros::pedidoExibirCabecalhoEmpresa($empresaA));
        $this->assertFalse(EmpresaParametros::pedidoExibirCabecalhoEmpresa($empresaB));

        EmpresaParametros::gravarPedidoExibirCabecalhoEmpresa($empresaA, false);
        $this->assertFalse(EmpresaParametros::pedidoExibirCabecalhoEmpresa($empresaA));
    }
}
