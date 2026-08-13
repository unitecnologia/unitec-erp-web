<?php

namespace App\Support\Erp;

class ErpAssetVersion
{
    /**
     * Versão do bundle autenticado (cache-bust de CSS/JS).
     * No cliente: evita glob+filemtime de ~100 arquivos a cada abertura de tela.
     */
    public static function bundle(): string
    {
        static $version = null;

        if ($version !== null) {
            return $version;
        }

        $appVersion = ErpUpdateService::readInstalledVersion() ?: '0';

        // Produção: versão do app + cache em disco (recalcula no máximo a cada 60s).
        if (! config('app.debug') && ! app()->environment(['local', 'testing'])) {
            $cached = self::readDiskCache($appVersion);
            if ($cached !== null) {
                return $version = $cached;
            }

            $mtime = self::computeMaxMtime();
            $computed = $appVersion.'-'.$mtime;
            self::writeDiskCache($appVersion, $mtime);

            return $version = $computed;
        }

        return $version = (string) self::computeMaxMtime();
    }

    private static function computeMaxMtime(): int
    {
        $mtime = 0;
        $paths = [
            public_path('css/erp-tokens.css'),
            public_path('css/erp-shell.css'),
            public_path('js/erp-shell.js'),
        ];

        foreach (glob(public_path('css/erp-*.css')) ?: [] as $path) {
            $paths[] = $path;
        }

        foreach (glob(public_path('js/erp-*.js')) ?: [] as $path) {
            $paths[] = $path;
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                $mtime = max($mtime, (int) filemtime($path));
            }
        }

        return $mtime > 0 ? $mtime : time();
    }

    private static function readDiskCache(string $appVersion): ?string
    {
        $file = storage_path('framework/cache/erp-asset-version.txt');

        if (! is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $parts = explode('|', trim($raw), 3);
        if (count($parts) < 3) {
            return null;
        }

        [$cachedApp, $mtime, $cachedAt] = $parts;

        if ($cachedApp !== $appVersion) {
            return null;
        }

        if ((time() - (int) $cachedAt) > 60) {
            return null;
        }

        return $cachedApp.'-'.$mtime;
    }

    private static function writeDiskCache(string $appVersion, int $mtime): void
    {
        $dir = storage_path('framework/cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $dir.DIRECTORY_SEPARATOR.'erp-asset-version.txt',
            $appVersion.'|'.$mtime.'|'.time()
        );
    }
}
