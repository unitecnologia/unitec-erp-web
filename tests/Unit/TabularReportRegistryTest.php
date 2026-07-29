<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Definitions\CurvaAbcReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TabularReportRegistryTest extends TestCase
{
    public function test_registry_contem_relatorios_de_produtos_vendas_e_financeiro(): void
    {
        $map = ReportRegistry::map();

        $this->assertArrayHasKey('curva-abc', $map);
        $this->assertArrayHasKey('historico-vendas', $map);
        $this->assertArrayHasKey('contas-receber', $map);
        $this->assertArrayHasKey('plano-contas', $map);
        $this->assertCount(33, $map);
        $this->assertTrue(ReportRegistry::has('conferencia-estoque'));
        $this->assertFalse(ReportRegistry::has('inexistente'));
    }

    public function test_curva_abc_resolve_colunas_padrao(): void
    {
        $report = new CurvaAbcReport;
        $columns = $report->resolveColumns(null);

        $this->assertSame($report->defaultColumns(), $columns);
        $this->assertContains('classe', $columns);
        $this->assertContains('receita', $columns);
    }

    public function test_period_from_request_aceita_carbon_do_erp_timezone(): void
    {
        $report = new class extends AbstractTabularReport
        {
            public function slug(): string
            {
                return 'smoke';
            }

            public function title(): string
            {
                return 'SMOKE';
            }

            public function permission(): string
            {
                return 'produtos.print';
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

        [$de, $ate] = $report->exposePeriod(Request::create('/admin/reports/r/smoke', 'GET'));

        $this->assertTrue($de->lessThanOrEqualTo($ate));
        $this->assertSame($de->format('Y-m'), $de->copy()->startOfMonth()->format('Y-m'));
    }
}
