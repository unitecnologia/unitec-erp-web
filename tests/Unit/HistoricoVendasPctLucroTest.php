<?php

namespace Tests\Unit;

use App\Support\Erp\Dre\VendaItemCustoHistorico;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaLucroPercentual;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaMargemBatch;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasReport;
use App\Support\Erp\Tef\TefMoney;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Illuminate\Http\Request;
use Tests\TestCase;

class HistoricoVendasPctLucroTest extends TestCase
{
    public function test_coluna_pct_lucro_existe_e_nao_fica_no_padrao(): void
    {
        $report = new HistoricoVendasReport;

        $this->assertArrayHasKey('pct_lucro', $report->columns());
        $this->assertSame('% LUCRO', $report->columns()['pct_lucro']);
        $this->assertNotContains('pct_lucro', $report->defaultColumns());
        $this->assertNotEmpty($report->columnWidthWeights());
        $this->assertContains('total', $report->nowrapColumns());
    }

    public function test_pct_lucro_so_entra_quando_flag_ligada_e_apos_total(): void
    {
        $report = new HistoricoVendasReport;
        $method = new \ReflectionMethod($report, 'resolveDetailColumns');
        $method->setAccessible(true);

        $colsOff = $method->invoke($report, Request::create('/r', 'GET', []), false);
        $colsOn = $method->invoke($report, Request::create('/r', 'GET', []), true);

        $this->assertNotContains('pct_lucro', $colsOff);
        $this->assertSame('pct_lucro', end($colsOn));
        $totalIdx = array_search('total', $colsOn, true);
        $this->assertSame($totalIdx + 1, array_search('pct_lucro', $colsOn, true));

        $src = file_get_contents(base_path('app/Support/Erp/Reports/Tabular/Definitions/HistoricoVendasReport.php'));
        $this->assertStringContainsString("'key' => 'exibir_pct_lucro'", $src);
        $this->assertStringContainsString('Exibir % de lucro', $src);
    }

    public function test_pct_lucro_usa_lucro_sobre_custo_igual_lucratividade_por_venda(): void
    {
        $ok = LucratividadeVendaCustoResolver::resolver([
            ['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '40.00'],
        ]);
        $this->assertTrue($ok['disponivel']);

        $bruto = TefMoney::normalize(100);
        $dev = TefMoney::normalize(10);
        $vendaLiquida = TefMoney::sub($bruto, $dev); // 90 (desc./acr. já no total; abate devolução)
        $custo = (float) $ok['custo_liquido']; // 40 D2.6

        // Relatórios: (90-40)/40 = 125%
        $pct = LucratividadeVendaLucroPercentual::sobreCusto($vendaLiquida, $custo);
        $this->assertSame(125.0, $pct);
        $this->assertSame('125,00%', LucratividadeVendaLucroPercentual::rotulo($vendaLiquida, $custo));

        // Tela/Pedido permanece sobre a venda (não usada nos relatórios).
        $this->assertSame(55.56, ForcaVendasMargemVendaCalculator::margemPercentual((float) $vendaLiquida, $custo));

        $falha = LucratividadeVendaCustoResolver::resolver([
            ['custo_situacao' => null, 'custo_total' => null],
        ]);
        $this->assertFalse($falha['disponivel']);
        $this->assertSame('Indisponível', LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL);
    }

    public function test_batch_vazio_nao_consulta_inventada(): void
    {
        $this->assertSame([], LucratividadeVendaMargemBatch::rotulosPorVenda([]));
    }
}
