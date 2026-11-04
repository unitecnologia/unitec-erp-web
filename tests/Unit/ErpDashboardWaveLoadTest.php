<?php

namespace Tests\Unit;

use App\Support\Erp\Dashboard\ErpDashboardCertificadoAlert;
use App\Support\Erp\Dashboard\ErpDashboardData;
use Tests\TestCase;

class ErpDashboardWaveLoadTest extends TestCase
{
    public function test_empty_payload_helpers(): void
    {
        $sales = ErpDashboardData::emptySalesChart();
        $this->assertSame('', $sales['defaultFrom']);
        $this->assertSame([], $sales['points']);

        $cash = ErpDashboardData::emptyCashflowChart();
        $this->assertSame([], $cash['labels']);
        $this->assertSame([], $cash['entradas']);

        $pie = ErpDashboardData::emptyPieChart('money');
        $this->assertSame('money', $pie['unit']);
        $this->assertSame([], $pie['values']);

        $alerts = ErpDashboardData::emptyAlerts();
        $this->assertSame([], $alerts['important']);
        $this->assertSame([], $alerts['estoque']);
    }

    public function test_certificado_alert_helpers(): void
    {
        $this->assertSame('red', ErpDashboardCertificadoAlert::alertTone(-1));
        $this->assertSame('red', ErpDashboardCertificadoAlert::alertTone(3));
        $this->assertSame('yellow', ErpDashboardCertificadoAlert::alertTone(15));
        $this->assertSame('green', ErpDashboardCertificadoAlert::alertTone(60));
        $this->assertSame('Certificado A1 vence hoje', ErpDashboardCertificadoAlert::alertTitle(0));
        $this->assertSame('Certificado A1 vence em 1 dia', ErpDashboardCertificadoAlert::alertTitle(1));
        $this->assertStringContainsString('vence em 10 dias', ErpDashboardCertificadoAlert::alertTitle(10));
        $this->assertStringContainsString('vencido há 2 dias', ErpDashboardCertificadoAlert::alertTitle(-2));
    }

    public function test_certificado_cache_forget_is_safe_for_invalid_empresa(): void
    {
        ErpDashboardCertificadoAlert::forgetCacheForEmpresa(0);
        ErpDashboardCertificadoAlert::forgetCacheForEmpresa(-5);
        $this->assertTrue(true);
    }
}
