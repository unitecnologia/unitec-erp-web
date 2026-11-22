<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\Tabular\Definitions\ProdutosLucratividadeReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMaisLucrativosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMaisVendidosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMenosLucrativosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMenosVendidosReport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LimitsRankingResultsTest extends TestCase
{
    public function test_padrao_dez_e_sugestoes(): void
    {
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit(null));
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit(''));
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit('abc'));
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit(0));
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit(-5));
        $this->assertSame([10, 20, 30, 50], ProdutosMaisVendidosReport::resultLimitSuggestions());
    }

    public function test_aceita_quantidades_validas_e_trava_maximo(): void
    {
        $this->assertSame(10, ProdutosMaisVendidosReport::normalizeResultLimit(10));
        $this->assertSame(20, ProdutosMaisVendidosReport::normalizeResultLimit('20'));
        $this->assertSame(30, ProdutosMaisVendidosReport::normalizeResultLimit(30));
        $this->assertSame(50, ProdutosMaisVendidosReport::normalizeResultLimit(50));
        $this->assertSame(15, ProdutosMaisVendidosReport::normalizeResultLimit(15));
        $this->assertSame(500, ProdutosMaisVendidosReport::normalizeResultLimit(9999));
        $this->assertSame(10, ProdutosLucratividadeReport::normalizeResultLimit(null));
    }

    public function test_cinco_relatorios_expoem_campo_quantidade(): void
    {
        $reports = [
            new ProdutosLucratividadeReport,
            new ProdutosMaisLucrativosReport,
            new ProdutosMenosLucrativosReport,
            new ProdutosMaisVendidosReport,
            new ProdutosMenosVendidosReport,
        ];

        foreach ($reports as $report) {
            $ref = new ReflectionMethod($report, 'resultLimitField');
            $ref->setAccessible(true);
            $field = $ref->invoke($report);

            $this->assertSame('qtd_resultados', $field['key'], $report->slug());
            $this->assertSame('number', $field['type'], $report->slug());
            $this->assertSame('Quantidade de resultados', $field['label'], $report->slug());
        }
    }

    public function test_apply_result_limit_corta_apos_ordenacao(): void
    {
        $report = new ProdutosMaisVendidosReport;
        $ref = new ReflectionMethod($report, 'applyResultLimit');
        $ref->setAccessible(true);

        $rows = [
            ['codigo' => '1'],
            ['codigo' => '2'],
            ['codigo' => '3'],
            ['codigo' => '4'],
        ];

        $this->assertSame(
            [['codigo' => '1'], ['codigo' => '2']],
            $ref->invoke($report, $rows, 2)
        );
        $this->assertSame($rows, $ref->invoke($report, $rows, 10));
    }
}
