<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Nfe;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;

final class ErpDashboardNfeRejeitadasAlert
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{tone: string, title: string, time: string, blink: bool}|null
     */
    public static function resolve(int|array|null $empresaScope = null): ?array
    {
        $count = self::countRejeitadas($empresaScope);

        if ($count <= 0) {
            return null;
        }

        return [
            'tone' => 'yellow',
            'title' => $count === 1
                ? '1 nota fiscal rejeitada na SEFAZ'
                : "{$count} notas fiscais rejeitadas na SEFAZ",
            'time' => 'Há 2 h',
            'blink' => true,
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function countRejeitadas(int|array|null $empresaScope = null): int
    {
        if ($empresaScope === null) {
            $empresaId = ErpDashboardCertificadoAlert::resolveEmpresaId();
            $empresaScope = ($empresaId && $empresaId > 0) ? $empresaId : null;
        }

        if ($empresaScope === null) {
            return 0;
        }

        $query = Nfe::query()->where('status', Nfe::STATUS_DENEGADA);
        ErpFinanceiroMetricas::applyEmpresaColumn($query, (new Nfe)->getTable(), $empresaScope);

        return (int) $query->count();
    }
}
