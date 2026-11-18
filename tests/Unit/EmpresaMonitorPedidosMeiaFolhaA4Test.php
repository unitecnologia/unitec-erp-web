<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sem MigratesSqliteMemory: parâmetro usa json_fallback (sem coluna/migration).
 */
class EmpresaMonitorPedidosMeiaFolhaA4Test extends TestCase
{
    public function test_parametro_no_catalogo_default_false_grupo_monitor_json_fallback(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = EmpresaParametros::COLUMN_MONITOR_PEDIDOS_MEIA_FOLHA_A4;

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertTrue($fields[$coluna]['json_fallback']);
        $this->assertSame('Padronizar impressão do pedido em meia folha A4', $fields[$coluna]['label']);
        $this->assertSame('monitor_vendas', EmpresaParametros::permissionGroupForField($coluna));
        $this->assertContains($coluna, EmpresaParametros::permissionFieldsJsonFallback());
    }

    public function test_gravacao_e_leitura_por_empresa_via_json_independente(): void
    {
        Storage::fake('local');

        $empresaA = 910011;
        $empresaB = 910012;

        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirMeiaFolhaA4($empresaA));
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirMeiaFolhaA4($empresaB));

        EmpresaParametros::gravarMonitorPedidosImprimirMeiaFolhaA4($empresaA, true);

        $this->assertTrue(EmpresaParametros::monitorPedidosImprimirMeiaFolhaA4($empresaA));
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirMeiaFolhaA4($empresaB));

        EmpresaParametros::gravarMonitorPedidosImprimirMeiaFolhaA4($empresaA, false);
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirMeiaFolhaA4($empresaA));
    }
}
