<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpContext;

final class ErpDashboardScope
{
    public const VISAO_EMPRESA = 'empresa';

    public const VISAO_GRUPO = 'grupo';

    /**
     * @return int|list<int>|null
     */
    public static function resolve(?int $empresaId = null, string $visao = self::VISAO_EMPRESA): int|array|null
    {
        if ($visao === self::VISAO_GRUPO) {
            $ids = ErpContext::accessibleEmpresaIds();

            return $ids !== [] ? $ids : null;
        }

        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

        return ($empresaId && $empresaId > 0) ? $empresaId : null;
    }

    public static function visaoLabel(string $visao, int|array|null $scope): ?string
    {
        if ($visao !== self::VISAO_GRUPO) {
            return null;
        }

        $count = is_array($scope) ? count($scope) : 0;

        return "Visão: Grupo ({$count} empresas)";
    }
}
