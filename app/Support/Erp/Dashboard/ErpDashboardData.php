<?php

namespace App\Support\Erp\Dashboard;

final class ErpDashboardData
{
    /**
     * @return array<string, mixed>
     */
    public static function all(?int $empresaId = null): array
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

        return [
            'kpis' => ErpDashboardKpis::build($empresaId),
            'salesChart' => ErpDashboardSalesChart::data(),
            'cashflowChart' => ErpDashboardCashflowChart::data(),
            'recentSales' => ErpDashboardRecentSales::list(),
            'alerts' => static::alerts($empresaId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function alerts(?int $empresaId): array
    {
        $important = [];

        $certAlert = ErpDashboardCertificadoAlert::fromEmpresa($empresaId);

        if ($certAlert !== null) {
            $important[] = $certAlert;
        }

        $nfeRejeitadasAlert = ErpDashboardNfeRejeitadasAlert::resolve($empresaId);

        if ($nfeRejeitadasAlert !== null) {
            $important[] = $nfeRejeitadasAlert;
        }

        $important[] = ErpDashboardBackupAlert::resolve();

        return [
            'important' => $important,
            'boletos' => ErpDashboardSidebarData::boletosVencidos(),
            'estoque' => ErpDashboardSidebarData::estoqueMinimo(),
        ];
    }
}
