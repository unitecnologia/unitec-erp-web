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
            return [
                'visao' => $collector->visao,
                'visaoLabel' => ErpDashboardScope::visaoLabel($collector->visao, $collector->empresaScope),
                'kpis' => ErpDashboardKpis::build($collector->empresaId, $collector->empresaScope),
                'gauges' => [],
                'sellerGauges' => [],
                'salesChart' => ['defaultFrom' => '', 'defaultTo' => '', 'points' => []],
                'cashflowChart' => ['labels' => [], 'entradas' => [], 'saidas' => []],
                'salesMixChart' => ['labels' => [], 'values' => [], 'colors' => []],
                'fiscalDocsChart' => ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'count'],
                'paymentMethodsChart' => ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'money'],
                'recentSales' => [],
                'highlights' => [],
                'alerts' => [
                    'important' => [],
                    'a_pagar_vencidos' => [],
                    'estoque' => [],
                ],
            ];
        }, $empresaId, $visao);
    }

    /**
     * Blocos pesados (gráficos, gauges, listas, alertas).
     *
     * @return array<string, mixed>
     */
    public static function heavy(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        return ErpDashboardCollector::run(function (ErpDashboardCollector $collector): array {
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
                'recentSales' => ErpDashboardRecentSales::list(6, $empresaScope),
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
                'recentSales' => ErpDashboardRecentSales::list(6, $empresaScope),
                'highlights' => ErpDashboardHighlights::build($empresaScope),
                'alerts' => static::alerts($empresaId, $empresaScope),
            ];
        }, $empresaId, $visao);
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
