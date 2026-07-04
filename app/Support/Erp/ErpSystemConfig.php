<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Support\Erp\Dashboard\ErpDashboardCertificadoAlert;

final class ErpSystemConfig
{
    public static function empresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

        if (! $empresaId) {
            return null;
        }

        return Empresa::query()->find($empresaId);
    }

    public static function updateDownloadUrl(?int $empresaId = null): string
    {
        $fromDb = trim((string) static::empresa($empresaId)?->param_update_download_url);

        if ($fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = trim((string) config('unitec.update_download_url', ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return rtrim((string) config('unitec.pagamento_url'), '/')
            .'/updates/'
            .urlencode((string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip'));
    }

    public static function backupEnabled(?int $empresaId = null): bool
    {
        $empresa = static::empresa($empresaId);

        if ($empresa !== null) {
            return (bool) $empresa->param_backup_habilitar;
        }

        return false;
    }

    public static function backupDestinationPath(?int $empresaId = null): string
    {
        return trim((string) static::empresa($empresaId)?->param_backup_pasta_destino);
    }

    public static function backupIntervalHours(?int $empresaId = null): int
    {
        $hours = (int) (static::empresa($empresaId)?->param_backup_intervalo_horas ?? 24);

        return max(1, $hours);
    }

    public static function backupLastStatus(?int $empresaId = null): string
    {
        $fromDb = trim((string) static::empresa($empresaId)?->param_backup_ultimo_status);

        if ($fromDb !== '') {
            return $fromDb;
        }

        return (string) config('unitec.backup_last_status', 'ok');
    }

    public static function backupLastAt(?int $empresaId = null): ?string
    {
        $fromDb = trim((string) static::empresa($empresaId)?->param_backup_ultimo_em);

        if ($fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = config('unitec.backup_last_at');

        return filled($fromEnv) ? (string) $fromEnv : null;
    }
}
