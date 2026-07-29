<?php

namespace Tests\Unit;

use App\Support\Erp\Dashboard\ErpDashboardGauges;
use Tests\TestCase;

class ErpDashboardSaudeEmpresaTest extends TestCase
{
    public function test_it_computes_weighted_health_score(): void
    {
        $score = ErpDashboardGauges::scoreFromFactors([
            ['key' => 'caixa', 'label' => 'Caixa', 'percent' => 100, 'weight' => 20],
            ['key' => 'estoque', 'label' => 'Estoque', 'percent' => 80, 'weight' => 20],
            ['key' => 'vendas', 'label' => 'Vendas', 'percent' => 70, 'weight' => 20],
            ['key' => 'inadimplencia', 'label' => 'Inadimplência', 'percent' => 90, 'weight' => 20],
            ['key' => 'lucro', 'label' => 'Lucro', 'percent' => 95, 'weight' => 20],
        ]);

        // (100+80+70+90+95) / 5 = 87
        $this->assertSame(87.0, $score['percent']);
        $this->assertCount(5, $score['factors']);
    }

    public function test_it_maps_health_status_bands(): void
    {
        $this->assertSame('Empresa saudável', ErpDashboardGauges::healthStatus(87)['label']);
        $this->assertSame('green', ErpDashboardGauges::healthStatus(87)['tone']);

        $this->assertSame('Atenção em alguns indicadores', ErpDashboardGauges::healthStatus(70)['label']);
        $this->assertSame('lime', ErpDashboardGauges::healthStatus(70)['tone']);

        $this->assertSame('Situação preocupante', ErpDashboardGauges::healthStatus(50)['label']);
        $this->assertSame('orange', ErpDashboardGauges::healthStatus(50)['tone']);

        $this->assertSame('Situação crítica', ErpDashboardGauges::healthStatus(20)['label']);
        $this->assertSame('red', ErpDashboardGauges::healthStatus(20)['tone']);
    }
}
