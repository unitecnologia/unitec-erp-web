<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\NfceRelatorioReport;
use PHPUnit\Framework\TestCase;

class NfceRelatorioReportTest extends TestCase
{
    public function test_report_title_for_tipo(): void
    {
        $this->assertSame(
            'RELATÓRIO DE NFC-e | RESUMIDO',
            NfceRelatorioReport::reportTitle(NfceRelatorioReport::TIPO_RESUMIDO),
        );
    }

    public function test_format_chave_acesso_groups_digits(): void
    {
        $this->assertSame(
            '4226 0722 4697 7200 0100 6500 1000 0000 0897 0931 6296',
            NfceRelatorioReport::formatChaveAcesso('42260722469772000100650010000000089709316296'),
        );
    }

    public function test_format_protocolo_keeps_digits(): void
    {
        $this->assertSame('142260174575849', NfceRelatorioReport::formatProtocolo('142260174575849'));
    }
}
