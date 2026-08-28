<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Support\Erp\Dashboard\ErpDashboardCertificadoAlert;

final class ErpSystemConfig
{
    public const DEFAULT_UPDATE_DOWNLOAD_URL = 'https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip';

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

        if ($fromDb !== '' && ! static::isDeadUpdateHost($fromDb)) {
            return $fromDb;
        }

        $fromEnv = trim((string) config('unitec.update_download_url', ''));

        if ($fromEnv !== '' && ! static::isDeadUpdateHost($fromEnv)) {
            return $fromEnv;
        }

        return self::DEFAULT_UPDATE_DOWNLOAD_URL;
    }

    public static function acessoRemotoHabilitado(?int $empresaId = null): bool
    {
        $empresa = static::empresa($empresaId);

        if ($empresa === null) {
            return true;
        }

        return (bool) ($empresa->param_acesso_remoto_habilitar ?? true);
    }

    public static function publicUrl(?int $empresaId = null): string
    {
        if (static::acessoRemotoHabilitado($empresaId)) {
            $fromDb = rtrim(trim((string) static::empresa($empresaId)?->param_erp_public_url), '/');

            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        return rtrim((string) config('app.url', ''), '/');
    }

    public static function gestorPublicUrl(?int $empresaId = null): string
    {
        if (static::acessoRemotoHabilitado($empresaId)) {
            $fromDb = rtrim(trim((string) static::empresa($empresaId)?->param_gestor_public_url), '/');

            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        $base = static::publicUrl($empresaId);

        return $base !== '' ? $base.'/gestor' : '';
    }

    private static function isDeadUpdateHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, [
            'unitecnologiasistemas.com.br',
            'www.unitecnologiasistemas.com.br',
        ], true);
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

    /**
     * Tamanho da letra da interface (px na raiz html). Padrão 14.
     */
    public static function uiFontSizePx(?int $empresaId = null): int
    {
        $empresa = static::empresa($empresaId);
        $raw = strtolower(trim((string) ($empresa?->param_ui_density ?? '')));

        $px = match ($raw) {
            'compact', 'compacto' => 13,
            'large', 'grande' => 18,
            'normal', '' => 14,
            default => (int) preg_replace('/\D/', '', $raw),
        };

        return max(12, min(24, $px > 0 ? $px : 14));
    }

    /**
     * @deprecated Use uiFontSizePx().
     */
    public static function uiDensity(?int $empresaId = null): string
    {
        return (string) static::uiFontSizePx($empresaId);
    }

    /**
     * @deprecated Use uiFontSizePx().
     */
    public static function uiDensityRootPercent(?string $density = null): float
    {
        $px = $density !== null && is_numeric($density)
            ? max(12, min(24, (int) $density))
            : static::uiFontSizePx();

        return round(($px / 16) * 100, 2);
    }

    /**
     * @deprecated Mantido por compatibilidade; preferir uiFontSizePx().
     */
    public static function browserZoom(?int $empresaId = null): int
    {
        return static::uiFontSizePx($empresaId);
    }

    /**
     * @deprecated Zoom por CSS/navegador foi substituído por tamanho tipográfico.
     */
    public static function syncBrowserZoomPreference(?int $zoom = null, ?int $empresaId = null): int
    {
        return static::uiFontSizePx($empresaId);
    }

    public static function browserZoomPreferencePath(): string
    {
        return storage_path('app/erp-browser-zoom.txt');
    }
}
