<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Support\Erp\ErpContext;
use Tests\TestCase;

/**
 * Isolamento do Monitor por empresa ativa (ErpContext) — asserts na SQL montada.
 * Totais 31/10: validação manual/DEV com MySQL (getEloquentQuery + session).
 */
class ForcaVendasMonitorEmpresaIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
    }

    protected function tearDown(): void
    {
        ErpContext::clearMemo();
        session()->forget('erp_empresa_id');
        parent::tearDown();
    }

    public function test_constrain_sem_empresa_gera_predicado_vazio(): void
    {
        session()->forget('erp_empresa_id');
        ErpContext::clearMemo();

        $sql = ForcaVendasMonitorResource::ordersQuery()->toSql();

        $this->assertStringContainsString('0 = 1', $sql);
    }

    public function test_constrain_com_empresa_filtra_empresa_id(): void
    {
        session(['erp_empresa_id' => 2]);
        ErpContext::clearMemo();

        $query = ForcaVendasMonitorResource::ordersQuery();
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('empresa_id', $sql);
        $this->assertContains(2, $bindings);
        $this->assertStringNotContainsString('0 = 1', $sql);
    }

    public function test_get_eloquent_query_mantem_tipo_pedido_e_empresa(): void
    {
        session(['erp_empresa_id' => 1]);
        ErpContext::clearMemo();

        $sql = ForcaVendasMonitorResource::getEloquentQuery()->toSql();

        $this->assertStringContainsString('empresa_id', $sql);
        $this->assertStringContainsString('tipo', $sql);
    }
}
