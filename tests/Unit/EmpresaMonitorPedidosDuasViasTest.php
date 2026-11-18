<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sem MigratesSqliteMemory: parâmetro usa json_fallback (sem coluna/migration).
 */
class EmpresaMonitorPedidosDuasViasTest extends TestCase
{
    public function test_parametro_no_catalogo_default_false_grupo_monitor_json_fallback(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = EmpresaParametros::COLUMN_MONITOR_PEDIDOS_DUAS_VIAS;

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertTrue($fields[$coluna]['json_fallback']);
        $this->assertSame('Imprimir 2 vias do pedido', $fields[$coluna]['label']);
        $this->assertSame('monitor_vendas', EmpresaParametros::permissionGroupForField($coluna));
        $this->assertContains($coluna, EmpresaParametros::permissionFieldsJsonFallback());
    }

    public function test_gravacao_e_leitura_por_empresa_via_json_independente(): void
    {
        Storage::fake('local');

        $empresaA = 910001;
        $empresaB = 910002;

        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirDuasVias($empresaA));
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirDuasVias($empresaB));

        EmpresaParametros::gravarMonitorPedidosImprimirDuasVias($empresaA, true);

        $this->assertTrue(EmpresaParametros::monitorPedidosImprimirDuasVias($empresaA));
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirDuasVias($empresaB));

        EmpresaParametros::gravarMonitorPedidosImprimirDuasVias($empresaA, false);
        $this->assertFalse(EmpresaParametros::monitorPedidosImprimirDuasVias($empresaA));
    }
}
