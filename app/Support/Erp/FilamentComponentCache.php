<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Cache de componentes Filament (bootstrap/cache/filament/panels).
 *
 * optimize:clear remove esse cache. Este helper reconstrói com lock
 * para evitar corridas e regenerações simultâneas.
 */
final class FilamentComponentCache
{
    private const LOCK_NAME = 'erp-filament-component-cache.lock';

    private const LOCK_TTL_SECONDS = 180;

    public static function panelsDirectory(?string $appPath = null): string
    {
        $root = $appPath !== null && $appPath !== ''
            ? rtrim($appPath, '\\/')
            : base_path();

        return $root.DIRECTORY_SEPARATOR.'bootstrap'
            .DIRECTORY_SEPARATOR.'cache'
            .DIRECTORY_SEPARATOR.'filament'
            .DIRECTORY_SEPARATOR.'panels';
    }

    public static function isPresent(?string $appPath = null): bool
    {
        $dir = self::panelsDirectory($appPath);

        if (! is_dir($dir)) {
            return false;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_file($dir.DIRECTORY_SEPARATOR.$file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Garante cache presente. Se já existir e $force=false, não reconstrói.
     *
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public static function ensure(?string $appPath = null, bool $force = false): array
    {
        if (! $force && self::isPresent($appPath)) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Cache Filament já presente.',
            ];
        }

        return self::rebuild($appPath, $force);
    }

    /**
     * Reconstrói icons + filament:cache-components com lock exclusivo.
     *
     * @return array{ok: bool, skipped: bool, message: string}
     */
    public static function rebuild(?string $appPath = null, bool $force = false): array
    {
        $lock = self::acquireLock($appPath);

        if ($lock === null) {
            // Outro processo está gerando — se o cache aparecer, ok; senão falha.
            $deadline = microtime(true) + 30;
            while (microtime(true) < $deadline) {
                if (self::isPresent($appPath)) {
                    return [
                        'ok' => true,
                        'skipped' => true,
                        'message' => 'Cache Filament gerado por outro processo.',
                    ];
                }
                usleep(200_000);
            }

            return [
                'ok' => false,
                'skipped' => false,
                'message' => 'Não foi possível obter lock para gerar cache Filament (outro processo em andamento).',
            ];
        }

        try {
            if (! $force && self::isPresent($appPath)) {
                return [
                    'ok' => true,
                    'skipped' => true,
                    'message' => 'Cache Filament já presente.',
                ];
            }

            if ($force) {
                self::clearPanelFiles($appPath);
            }

            try {
                Artisan::call('icons:cache');
            } catch (Throwable $e) {
                // icons:cache é desejável; o ganho crítico é filament:cache-components.
                report($e);
            }

            Artisan::call('filament:cache-components');

            if (! self::isPresent($appPath)) {
                return [
                    'ok' => false,
                    'skipped' => false,
                    'message' => 'filament:cache-components terminou sem gerar bootstrap/cache/filament/panels.',
                ];
            }

            return [
                'ok' => true,
                'skipped' => false,
                'message' => 'Cache Filament reconstruído.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'skipped' => false,
                'message' => $e->getMessage(),
            ];
        } finally {
            self::releaseLock($lock, $appPath);
        }
    }

    public static function clearPanelFiles(?string $appPath = null): void
    {
        $dir = self::panelsDirectory($appPath);

        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return resource|null
     */
    private static function acquireLock(?string $appPath)
    {
        $path = self::lockPath($appPath);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            return null;
        }

        $deadline = microtime(true) + self::LOCK_TTL_SECONDS;
        while (microtime(true) < $deadline) {
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                ftruncate($handle, 0);
                fwrite($handle, (string) getmypid());
                fflush($handle);

                return $handle;
            }

            usleep(100_000);
        }

        fclose($handle);

        return null;
    }

    /**
     * @param  resource  $handle
     */
    private static function releaseLock($handle, ?string $appPath): void
    {
        try {
            flock($handle, LOCK_UN);
        } catch (Throwable) {
            //
        }

        fclose($handle);
    }

    private static function lockPath(?string $appPath): string
    {
        $root = $appPath !== null && $appPath !== ''
            ? rtrim($appPath, '\\/')
            : base_path();

        return $root.DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.self::LOCK_NAME;
    }
}
