<?php

namespace App\Support\Erp\Dashboard;

final class ErpDashboardData
{
    /**
     * @return array<string, mixed>
     */
    public static function all(?int $empresaId = null, string $visao = ErpDashboardScope::VISAO_EMPRESA): array
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
        $empresaScope = ErpDashboardScope::resolve($empresaId, $visao);

        return [
            'visao' => $visao,
            'visaoLabel' => ErpDashboardScope::visaoLabel($visao, $empresaScope),
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
