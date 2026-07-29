<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\NfceRelatorioReportService;
use PHPUnit\Framework\TestCase;

class NfceRelatorioReportServiceTest extends TestCase
{
    public function test_competencia_period(): void
    {
        $periodo = NfceRelatorioReportService::competenciaPeriod('2023-05');

        $this->assertSame('2023-05-01', $periodo['de']);
        $this->assertSame('2023-05-31', $periodo['ate']);
        $this->assertSame('05/2023', $periodo['labelShort']);
    }
}
