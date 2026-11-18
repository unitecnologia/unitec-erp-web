<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sem MigratesSqliteMemory: parâmetro usa json_fallback (sem coluna/migration).
 */
class EmpresaPdvSolicitarVendedorCadaVendaTest extends TestCase
{
    public function test_parametro_no_catalogo_default_false_grupo_pdv_json_fallback(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = EmpresaParametros::COLUMN_SOLICITAR_VENDEDOR_CADA_VENDA;

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertTrue($fields[$coluna]['json_fallback']);
        $this->assertSame('Solicitar vendedor a cada venda', $fields[$coluna]['label']);
        $this->assertSame('pdv', EmpresaParametros::permissionGroupForField($coluna));
        $this->assertContains($coluna, EmpresaParametros::permissionFieldsJsonFallback());
    }

    public function test_gravacao_e_leitura_por_empresa_via_json_independente(): void
    {
        Storage::fake('local');

        $empresaA = 920011;
        $empresaB = 920012;

        $this->assertFalse(EmpresaParametros::solicitarVendedorCadaVenda($empresaA));
        $this->assertFalse(EmpresaParametros::solicitarVendedorCadaVenda($empresaB));

        EmpresaParametros::gravarSolicitarVendedorCadaVenda($empresaA, true);

        $this->assertTrue(EmpresaParametros::solicitarVendedorCadaVenda($empresaA));
        $this->assertFalse(EmpresaParametros::solicitarVendedorCadaVenda($empresaB));

        EmpresaParametros::gravarSolicitarVendedorCadaVenda($empresaA, false);
        $this->assertFalse(EmpresaParametros::solicitarVendedorCadaVenda($empresaA));
    }
}
