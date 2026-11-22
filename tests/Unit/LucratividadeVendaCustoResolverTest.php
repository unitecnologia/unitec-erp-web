<?php

namespace Tests\Unit;

use App\Support\Erp\Dre\VendaItemCustoHistorico;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\Tabular\Definitions\LucratividadePorVendaReport;
use PHPUnit\Framework\TestCase;

class LucratividadeVendaCustoResolverTest extends TestCase
{
    public function test_custo_completo_libera_liquido(): void
    {
        $r = LucratividadeVendaCustoResolver::resolver([
            ['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '10.00'],
            ['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '5.50'],
        ]);

        $this->assertTrue($r['disponivel']);
        $this->assertSame('15.50', $r['custo_liquido']);
        $this->assertSame('', $r['pendencia']);
    }

    public function test_sem_captura_fica_indisponivel_sem_fallback(): void
    {
        $r = LucratividadeVendaCustoResolver::resolver([
            ['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '10.00'],
            ['custo_situacao' => null, 'custo_total' => null],
        ]);

        $this->assertFalse($r['disponivel']);
        $this->assertNull($r['custo_liquido']);
        $this->assertStringContainsString('sem captura', mb_strtolower($r['pendencia']));
    }

    public function test_somente_servicos_libera_custo_zero(): void
    {
        $r = LucratividadeVendaCustoResolver::resolver([
            ['custo_situacao' => VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL, 'custo_total' => null],
        ]);

        $this->assertTrue($r['disponivel']);
        $this->assertSame('0.00', $r['custo_liquido']);
    }

    public function test_devolucao_sem_custo_bloqueia_mesmo_com_venda_ok(): void
    {
        $r = LucratividadeVendaCustoResolver::resolver(
            [['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '20.00']],
            [['custo_situacao' => null, 'custo_total' => null]],
        );

        $this->assertFalse($r['disponivel']);
        $this->assertSame('20.00', $r['custo_bruto']);
        $this->assertNull($r['custo_liquido']);
    }

    public function test_devolucao_com_custo_abate_liquido(): void
    {
        $r = LucratividadeVendaCustoResolver::resolver(
            [['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '20.00']],
            [['custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK, 'custo_total' => '7.00']],
        );

        $this->assertTrue($r['disponivel']);
        $this->assertSame('13.00', $r['custo_liquido']);
        $this->assertSame('7.00', $r['custo_devolucoes']);
    }

    public function test_relatorio_exige_gerar_explicito(): void
    {
        $report = new LucratividadePorVendaReport;
        $this->assertSame('lucratividade-por-venda', $report->slug());
        $this->assertTrue($report->requiresExplicitGenerate());
        $this->assertContains('total_liquido', $report->defaultColumns());
        $this->assertContains('custo_historico', $report->defaultColumns());
    }

    public function test_colunas_iniciais_padrao_todas_marcadas(): void
    {
        $report = new LucratividadePorVendaReport;
        $defaults = $report->defaultColumns();

        $this->assertSame(array_keys($report->columns()), $defaults);
        $this->assertSame($defaults, $report->resolveColumns(null));
        $this->assertSame($defaults, $report->resolveColumns([]));
        $this->assertSame(
            ['numero', 'data', 'cliente'],
            $report->resolveColumns(['numero', 'data', 'cliente', 'inexistente'])
        );
    }

    public function test_tipo_relatorio_padrao_detalhado_e_resumido_valido(): void
    {
        $this->assertSame(
            LucratividadePorVendaReport::TIPO_DETALHADO,
            LucratividadePorVendaReport::normalizeTipo('')
        );
        $this->assertSame(
            LucratividadePorVendaReport::TIPO_DETALHADO,
            LucratividadePorVendaReport::normalizeTipo('xyz')
        );
        $this->assertSame(
            LucratividadePorVendaReport::TIPO_RESUMIDO,
            LucratividadePorVendaReport::normalizeTipo('resumido')
        );
        $this->assertArrayHasKey('qtd_vendas', (new LucratividadePorVendaReport)->resumidoColumns());
    }

    public function test_resumido_nao_inventa_lucro_quando_custo_indisponivel(): void
    {
        $report = new LucratividadePorVendaReport;
        $row = $report->formatResumidoRow(12, '1000.00', [
            'disponivel' => false,
            'custo_liquido' => '0.00',
            'lucro' => '999.00',
            'margem' => 99.9,
            'pendencia' => 'Há vendas sem custo histórico completo no período.',
        ]);

        $this->assertSame('12', $row['qtd_vendas']);
        $this->assertSame('1.000,00', $row['total_liquido']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $row['custo_historico']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $row['lucro']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $row['margem']);
    }

    public function test_resumido_exibe_lucro_quando_custo_disponivel(): void
    {
        $report = new LucratividadePorVendaReport;
        $row = $report->formatResumidoRow(3, '300.00', [
            'disponivel' => true,
            'custo_liquido' => '120.00',
            'lucro' => '180.00',
            'margem' => 60.0,
            'pendencia' => '',
        ]);

        $this->assertSame('3', $row['qtd_vendas']);
        $this->assertSame('300,00', $row['total_liquido']);
        $this->assertSame('120,00', $row['custo_historico']);
        $this->assertSame('180,00', $row['lucro']);
        $this->assertSame('60,00%', $row['margem']);
    }
}
