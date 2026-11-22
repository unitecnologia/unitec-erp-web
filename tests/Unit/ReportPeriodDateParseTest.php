<?php

namespace Tests\Unit;

use App\Support\Erp\ErpMenu;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ReportPeriodDateParseTest extends TestCase
{
    public function test_parse_date_aceita_formato_brasileiro_do_datepicker(): void
    {
        $report = new class extends AbstractTabularReport
        {
            public function slug(): string
            {
                return 'smoke-date';
            }

            public function title(): string
            {
                return 'Smoke';
            }

            public function permission(): string
            {
                return 'vendas.print';
            }

            public function columns(): array
            {
                return ['a' => 'A'];
            }

            public function defaultColumns(): array
            {
                return ['a'];
            }

            public function filterFields(): array
            {
                return [];
            }

            public function build(Request $request): array
            {
                return $this->result([], ['a'], [], []);
            }

            /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} */
            public function exposePeriod(Request $request): array
            {
                return $this->periodFromRequest($request);
            }
        };

        $request = Request::create('/admin/reports/r/smoke', 'GET', [
            'de' => '20/11/2026',
            'ate' => '20/11/2026',
        ]);

        [$de, $ate] = $report->exposePeriod($request);

        $this->assertSame('2026-11-20', $de->toDateString());
        $this->assertSame('2026-11-20', $ate->toDateString());
    }

    public function test_menu_marca_urls_de_relatorio_como_full_page(): void
    {
        $this->assertTrue(ErpMenu::isFullPageReportUrl('/admin/relatorio-lucratividade-por-venda'));
        $this->assertTrue(ErpMenu::isFullPageReportUrl('/admin/reports/r/curva-abc'));
        $this->assertTrue(ErpMenu::isFullPageReportUrl('http://127.0.0.1:8000/admin/reports/orcamento/1'));
        $this->assertFalse(ErpMenu::isFullPageReportUrl('/admin/vendas'));
        $this->assertFalse(ErpMenu::isFullPageReportUrl(null));
    }
}
