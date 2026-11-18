<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sem MigratesSqliteMemory: parâmetro usa json_fallback (sem coluna/migration).
 */
class EmpresaForcaVendasAlterarPrecoUnitarioTest extends TestCase
{
    public function test_parametro_no_catalogo_default_false_grupo_forca_vendas_json_fallback(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = EmpresaParametros::COLUMN_FORCA_VENDAS_ALTERAR_PRECO_UNITARIO;

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertTrue($fields[$coluna]['json_fallback']);
        $this->assertSame('Permitir alterar preço unitário na venda', $fields[$coluna]['label']);
        $this->assertSame('forca_vendas', EmpresaParametros::permissionGroupForField($coluna));
        $this->assertContains($coluna, EmpresaParametros::permissionFieldsJsonFallback());
    }

    public function test_gravacao_e_leitura_por_empresa_via_json_independente(): void
    {
        Storage::fake('local');

        $empresaA = 920001;
        $empresaB = 920002;

        $this->assertFalse(EmpresaParametros::forcaVendasAlterarPrecoUnitario($empresaA));
        $this->assertFalse(EmpresaParametros::forcaVendasAlterarPrecoUnitario($empresaB));

        EmpresaParametros::gravarForcaVendasAlterarPrecoUnitario($empresaA, true);

        $this->assertTrue(EmpresaParametros::forcaVendasAlterarPrecoUnitario($empresaA));
        $this->assertFalse(EmpresaParametros::forcaVendasAlterarPrecoUnitario($empresaB));

        EmpresaParametros::gravarForcaVendasAlterarPrecoUnitario($empresaA, false);
        $this->assertFalse(EmpresaParametros::forcaVendasAlterarPrecoUnitario($empresaA));
    }
}
