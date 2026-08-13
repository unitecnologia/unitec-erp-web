<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Nfe;

final class ErpDashboardNfeRejeitadasAlert
{
    /**
     * @return array{tone: string, title: string, time: string, blink: bool}|null
     */
    public static function resolve(?int $empresaId = null): ?array
    {
        $count = self::countRejeitadas($empresaId);

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

    private static function countRejeitadas(?int $empresaId): int
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

        if ($empresaId) {
            $real = Nfe::query()
                ->where('empresa_id', $empresaId)
                ->where('status', Nfe::STATUS_DENEGADA)
                ->count();

            return $real;
        }

        return 0;
    }
}
