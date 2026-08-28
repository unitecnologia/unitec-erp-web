<?php

namespace App\Support\Erp;

use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Dispara exclusivamente o apply da árvore já depositada em atualizacao/.
 */
final class ErpUpdateProcessLauncher
{
    public static function launch(string $appPath): bool
    {
        $appPath = rtrim($appPath, '\\/');
        $php = self::resolvePhpBinary($appPath);
        $artisan = $appPath.DIRECTORY_SEPARATOR.'artisan';
        $logFile = storage_path('logs/erp-atualizacao-apply.log');

        if (! is_file($artisan) || ! self::isUsablePhpBinary($php)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($logFile));

        if (PHP_OS_FAMILY === 'Windows') {
            $batch = storage_path('app/private/erp-atualizacao-apply.bat');
            $quote = static fn (string $value): string => str_replace('"', '""', $value);
            File::put($batch, implode("\r\n", [
                '@echo off',
                'chcp 65001 >nul',
                'cd /d "'.$quote($appPath).'"',
                '"'.$quote($php).'" -d opcache.enable=0 -d opcache.enable_cli=0 "'.$quote($artisan).'" unitec:apply-atualizacao --app-path="'.$quote($appPath).'" >> "'.$quote($logFile).'" 2>&1',
            ])."\r\n");

            $handle = @popen('start "" /B cmd /C '.escapeshellarg($batch), 'r');
            if ($handle === false) {
                return false;
            }

            pclose($handle);
        } else {
            try {
                Process::path($appPath)
                    ->timeout(null)
                    ->start([$php, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', $artisan, 'unitec:apply-atualizacao', '--app-path='.$appPath]);
            } catch (\Throwable) {
                return false;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $state = (string) (AtualizacaoApplyService::readProgress($appPath)['state'] ?? 'idle');
            if (in_array($state, ['starting', 'copying', 'discovering', 'migrating', 'caching', 'finalizing', 'completed', 'failed'], true)) {
                return true;
            }

            usleep(500_000);
        }

        return false;
    }

    /**
     * Gera caches Filament em background (não bloqueia o modal de atualização).
     */
    public static function launchFilamentCaches(string $appPath): bool
    {
        $appPath = rtrim($appPath, '\\/');
        $php = self::resolvePhpBinary($appPath);
        $artisan = $appPath.DIRECTORY_SEPARATOR.'artisan';
        $logFile = $appPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'
            .DIRECTORY_SEPARATOR.'erp-filament-cache.log';

        if (! is_file($artisan) || ! self::isUsablePhpBinary($php)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($logFile));

        if (PHP_OS_FAMILY === 'Windows') {
            $batch = $appPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
                .DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'erp-filament-cache.bat';
            $quote = static fn (string $value): string => str_replace('"', '""', $value);
            File::put($batch, implode("\r\n", [
                '@echo off',
                'chcp 65001 >nul',
                'cd /d "'.$quote($appPath).'"',
                'echo [%date% %time%] Iniciando caches Filament >> "'.$quote($logFile).'"',
                '"'.$quote($php).'" -d opcache.enable=0 -d opcache.enable_cli=0 "'.$quote($artisan).'" icons:cache >> "'.$quote($logFile).'" 2>&1',
                '"'.$quote($php).'" -d opcache.enable=0 -d opcache.enable_cli=0 "'.$quote($artisan).'" filament:cache-components >> "'.$quote($logFile).'" 2>&1',
                'echo [%date% %time%] Caches Filament concluidos >> "'.$quote($logFile).'"',
            ])."\r\n");

            $handle = @popen('start "" /B cmd /C '.escapeshellarg($batch), 'r');
            if ($handle === false) {
                return false;
            }

            pclose($handle);

            return true;
        }

        try {
            Process::path($appPath)
                ->timeout(null)
                ->start([$php, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', $artisan, 'icons:cache']);
            Process::path($appPath)
                ->timeout(null)
                ->start([$php, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', $artisan, 'filament:cache-components']);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public static function resolvePhpBinary(string $appPath): string
    {
        $candidates = [
            $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'php.exe',
            PHP_BINARY ?: '',
        ];

        foreach ($candidates as $candidate) {
            if (self::isUsablePhpBinary($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    private static function isUsablePhpBinary(string $path): bool
    {
        if ($path === 'php') {
            return true;
        }

        return $path !== '' && is_file($path)
            && ! str_contains(strtolower($path), 'php-cgi')
            && ! str_contains(strtolower($path), 'php-fpm');
    }
}
