<?php

namespace App\Support\Erp\Dashboard;

final class ErpDashboardData
{
    /**
     * Dados leves para o first paint (KPIs + metadados de visão).
     *
     * @return array<string, mixed>
     */
    public static function shell(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
            $kpis = ErpDashboardKpis::build($collector->empresaId, $collector->empresaScope);
            $receber = $collector->receberVencido();
            $pagar = $collector->pagarVencido();

            return [
                'visao' => $collector->visao,
                'visaoLabel' => ErpDashboardScope::visaoLabel($collector->visao, $collector->empresaScope),
                'kpis' => $kpis,
                'gauges' => [],
                'sellerGauges' => [],
                'salesChart' => static::emptySalesChart(),
                'cashflowChart' => static::emptyCashflowChart(),
                'salesMixChart' => static::emptyPieChart(),
                'fiscalDocsChart' => static::emptyPieChart('count'),
                'paymentMethodsChart' => static::emptyPieChart('money'),
                'recentSales' => [],
                'highlights' => [],
                'alerts' => static::emptyAlerts(),
                // Agregados crus do shell para o heavy reutilizar no mesmo carregamento (sem cache persistente).
                '_seed' => [
                    'saldo_caixa' => $collector->saldoCaixa(),
                    'receber_vencido' => (float) $receber['valor'],
                    'receber_vencido_qtd' => (int) $receber['qtd'],
                    'pagar_vencido' => (float) $pagar['valor'],
                    'pagar_vencido_qtd' => (int) $pagar['qtd'],
                    'faturamento_mes' => $collector->faturamentoPeriodo(),
                ],
            ];
        }, $empresaId, $visao);
    }

    /**
     * Onda 1 — gauges (Saúde, metas, margem, estoque) + metas de vendedores.
     *
     * @return array{gauges: list<array<string, mixed>>, sellerGauges: list<array<string, mixed>>}
     */
    public static function waveGauges(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
            return [
                'gauges' => ErpDashboardGauges::build($collector->empresaId, $collector->empresaScope),
                'sellerGauges' => ErpDashboardGauges::buildVendedores($collector->empresaId, $collector->empresaScope),
            ];
        }, $empresaId, $visao);
    }

    /**
     * Onda 2 — gráficos principais (vendas por período + entradas/saídas).
     *
     * @return array{salesChart: array<string, mixed>, cashflowChart: array<string, mixed>}
     */
    public static function waveCharts(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
            return [
                'salesChart' => ErpDashboardSalesChart::data(null, null, $collector->empresaScope),
                'cashflowChart' => ErpDashboardCashflowChart::data($collector->empresaScope),
            ];
        }, $empresaId, $visao);
    }

    /**
     * Onda 3 — pizzas, listas, destaques e alertas laterais.
     *
     * @return array{
     *     salesMixChart: array<string, mixed>,
     *     fiscalDocsChart: array<string, mixed>,
     *     paymentMethodsChart: array<string, mixed>,
     *     recentSales: list<array<string, mixed>>,
     *     highlights: list<array<string, mixed>>,
     *     alerts: array<string, mixed>
     * }
     */
    public static function waveSecondary(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
            return [
                'salesMixChart' => ErpDashboardSalesMixChart::data($collector->empresaScope),
                'fiscalDocsChart' => ErpDashboardFiscalDocsChart::data($collector->empresaScope),
                'paymentMethodsChart' => ErpDashboardPaymentMethodsChart::data($collector->empresaScope),
                'recentSales' => ErpDashboardRecentSales::list(5, $collector->empresaScope),
                'highlights' => ErpDashboardHighlights::build($collector->empresaScope),
                'alerts' => static::alerts($collector->empresaId, $collector->empresaScope),
            ];
        }, $empresaId, $visao);
    }

    /**
     * Blocos pesados (compat: uma única coleta com as 3 ondas).
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>|null  $seed  agregados do shell (mesmo carregamento)
     */
    public static function heavy(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA, ?array $seed = null): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector) use ($seed): array {
            if (is_array($seed) && $seed !== []) {
                $collector->seed($seed);
            }

            $empresaId = $collector->empresaId;
            $empresaScope = $collector->empresaScope;

            return [
                'gauges' => ErpDashboardGauges::build($empresaId, $empresaScope),
                'sellerGauges' => ErpDashboardGauges::buildVendedores($empresaId, $empresaScope),
                'salesChart' => ErpDashboardSalesChart::data(null, null, $empresaScope),
                'cashflowChart' => ErpDashboardCashflowChart::data($empresaScope),
                'salesMixChart' => ErpDashboardSalesMixChart::data($empresaScope),
                'fiscalDocsChart' => ErpDashboardFiscalDocsChart::data($empresaScope),
                'paymentMethodsChart' => ErpDashboardPaymentMethodsChart::data($empresaScope),
                'recentSales' => ErpDashboardRecentSales::list(5, $empresaScope),
                'highlights' => ErpDashboardHighlights::build($empresaScope),
                'alerts' => static::alerts($empresaId, $empresaScope),
            ];
        }, $empresaId, $visao);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
            $empresaId = $collector->empresaId;
            $empresaScope = $collector->empresaScope;

            return [
                'visao' => $collector->visao,
                'visaoLabel' => ErpDashboardScope::visaoLabel($collector->visao, $empresaScope),
                'kpis' => ErpDashboardKpis::build($empresaId, $empresaScope),
                'gauges' => ErpDashboardGauges::build($empresaId, $empresaScope),
                'sellerGauges' => ErpDashboardGauges::buildVendedores($empresaId, $empresaScope),
                'salesChart' => ErpDashboardSalesChart::data(null, null, $empresaScope),
                'cashflowChart' => ErpDashboardCashflowChart::data($empresaScope),
                'salesMixChart' => ErpDashboardSalesMixChart::data($empresaScope),
                'fiscalDocsChart' => ErpDashboardFiscalDocsChart::data($empresaScope),
                'paymentMethodsChart' => ErpDashboardPaymentMethodsChart::data($empresaScope),
                'recentSales' => ErpDashboardRecentSales::list(5, $empresaScope),
                'highlights' => ErpDashboardHighlights::build($empresaScope),
                'alerts' => static::alerts($empresaId, $empresaScope),
            ];
        }, $empresaId, $visao);
    }

    /**
     * @return array{defaultFrom: string, defaultTo: string, points: list<mixed>}
     */
    public static function emptySalesChart(): array
    {
        return ['defaultFrom' => '', 'defaultTo' => '', 'points' => []];
    }

    /**
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>}
     */
    public static function emptyCashflowChart(): array
    {
        return ['labels' => [], 'entradas' => [], 'saidas' => []];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit?: string}
     */
    public static function emptyPieChart(string $unit = ''): array
    {
        $pie = ['labels' => [], 'values' => [], 'colors' => []];

        if ($unit !== '') {
            $pie['unit'] = $unit;
        }

        return $pie;
    }

    /**
     * @return array{important: list<mixed>, a_pagar_vencidos: list<mixed>, estoque: list<mixed>}
     */
    public static function emptyAlerts(): array
    {
        return [
            'important' => [],
            'a_pagar_vencidos' => [],
            'estoque' => [],
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function alerts(?int $empresaId, int|array|null $empresaScope = null): array
    {
        $important = [];

        $certAlert = ErpDashboardCertificadoAlert::fromEmpresa($empresaId);

        if ($certAlert !== null) {
            $important[] = $certAlert;
        }

        $nfeRejeitadasAlert = ErpDashboardNfeRejeitadasAlert::resolve($empresaScope ?? $empresaId);

        if ($nfeRejeitadasAlert !== null) {
            $important[] = $nfeRejeitadasAlert;
        }

        $important[] = ErpDashboardBackupAlert::resolve();

        return [
            'important' => $important,
            'a_pagar_vencidos' => ErpDashboardSidebarData::contasPagarVencidas(50, $empresaScope),
            'estoque' => ErpDashboardSidebarData::estoqueMinimo(),
        ];
    }
}
