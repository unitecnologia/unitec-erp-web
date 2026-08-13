<?php

namespace App\Support\Erp\Reports\Tabular;

use App\Support\Erp\Reports\Tabular\Definitions\BalancoFinanceiroReport;
use App\Support\Erp\Reports\Tabular\Definitions\ConferenciaEstoqueReport;
use App\Support\Erp\Reports\Tabular\Definitions\ContasPagarReport;
use App\Support\Erp\Reports\Tabular\Definitions\ContasReceberReport;
use App\Support\Erp\Reports\Tabular\Definitions\CurvaAbcReport;
use App\Support\Erp\Reports\Tabular\Definitions\EstoqueComposicaoReport;
use App\Support\Erp\Reports\Tabular\Definitions\EstoqueGradeReport;
use App\Support\Erp\Reports\Tabular\Definitions\EstoqueMinimoReport;
use App\Support\Erp\Reports\Tabular\Definitions\EstoqueNegativoReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoComprasFornecedorReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoComprasReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoOrcamentosReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoProdutosReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasClienteReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasReport;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasVendedorReport;
use App\Support\Erp\Reports\Tabular\Definitions\MovimentoCaixaReport;
use App\Support\Erp\Reports\Tabular\Definitions\OutrasSaidasMovimentoReport;
use App\Support\Erp\Reports\Tabular\Definitions\PlanoContasReport;
use App\Support\Erp\Reports\Tabular\Definitions\PrecoAlteradoReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosLucratividadeReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMaisLucrativosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMaisVendidosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMenosLucrativosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMenosVendidosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ResumoCaixaReport;
use App\Support\Erp\Reports\Tabular\Definitions\ResumoFinanceiroContaReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasCfopCsosnReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasFormaPagamentoReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasPdvReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasProdutosClientesReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasProdutosGeralReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasProdutosMonofasicaReport;
use App\Support\Erp\Reports\Tabular\Definitions\VendasProdutosVendedoresReport;
use InvalidArgumentException;

class ReportRegistry
{
    /**
     * @return array<string, class-string<TabularReportDefinition>>
     */
    public static function map(): array
    {
        return [
            'curva-abc' => CurvaAbcReport::class,
            'historico-produtos' => HistoricoProdutosReport::class,
            'historico-compras' => HistoricoComprasReport::class,
            'historico-compras-fornecedor' => HistoricoComprasFornecedorReport::class,
            'produtos-lucratividade' => ProdutosLucratividadeReport::class,
            'produtos-menos-lucrativos' => ProdutosMenosLucrativosReport::class,
            'produtos-mais-lucrativos' => ProdutosMaisLucrativosReport::class,
            'produtos-menos-vendidos' => ProdutosMenosVendidosReport::class,
            'produtos-mais-vendidos' => ProdutosMaisVendidosReport::class,
            'preco-alterado' => PrecoAlteradoReport::class,
            'estoque-composicao' => EstoqueComposicaoReport::class,
            'estoque-grade' => EstoqueGradeReport::class,
            'estoque-minimo' => EstoqueMinimoReport::class,
            'estoque-negativo' => EstoqueNegativoReport::class,
            'conferencia-estoque' => ConferenciaEstoqueReport::class,
            'historico-vendas' => HistoricoVendasReport::class,
            'historico-orcamentos' => HistoricoOrcamentosReport::class,
            'historico-vendas-cliente' => HistoricoVendasClienteReport::class,
            'historico-vendas-vendedor' => HistoricoVendasVendedorReport::class,
            'vendas-pdv' => VendasPdvReport::class,
            'vendas-forma-pagamento' => VendasFormaPagamentoReport::class,
            'vendas-produtos-geral' => VendasProdutosGeralReport::class,
            'vendas-produtos-clientes' => VendasProdutosClientesReport::class,
            'vendas-produtos-vendedores' => VendasProdutosVendedoresReport::class,
            'vendas-cfop-csosn' => VendasCfopCsosnReport::class,
            'vendas-produtos-monofasica' => VendasProdutosMonofasicaReport::class,
            'contas-receber' => ContasReceberReport::class,
            'contas-pagar' => ContasPagarReport::class,
            'resumo-caixa' => ResumoCaixaReport::class,
            'movimento-caixa' => MovimentoCaixaReport::class,
            'outras-saidas-movimento' => OutrasSaidasMovimentoReport::class,
            'balanco-financeiro' => BalancoFinanceiroReport::class,
            'resumo-financeiro-conta' => ResumoFinanceiroContaReport::class,
            'plano-contas' => PlanoContasReport::class,
        ];
    }

    public static function make(string $slug): TabularReportDefinition
    {
        $map = static::map();

        if (! isset($map[$slug])) {
            throw new InvalidArgumentException("Relatório desconhecido: {$slug}");
        }

        /** @var TabularReportDefinition $report */
        $report = app($map[$slug]);

        return $report;
    }

    public static function has(string $slug): bool
    {
        return isset(static::map()[$slug]);
    }

    public static function route(string $slug): string
    {
        return route('erp.reports.tabular', ['slug' => $slug]);
    }
}
