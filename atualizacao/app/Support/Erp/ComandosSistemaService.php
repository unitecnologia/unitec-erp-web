<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ComandosSistemaService
{
    /**
     * @return array{ok: bool, message: string, cleared: list<string>}
     */
    public function limparCache(): array
    {
        $cleared = [];

        try {
            Artisan::call('optimize:clear');
            $cleared[] = 'Caches Laravel (config, rotas, views, eventos)';
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Falha ao limpar cache: '.$e->getMessage(),
                'cleared' => $cleared,
            ];
        }

        try {
            Cache::flush();
            $cleared[] = 'Cache em memória / driver';
        } catch (Throwable $e) {
            report($e);
        }

        try {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $opcacheDir = base_path('tools/php/opcache');
            if (is_dir($opcacheDir)) {
                foreach (glob($opcacheDir.DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
                    if (is_file($entry)) {
                        @unlink($entry);
                    }
                }
            }
            $cleared[] = 'OPcache (versão do ERP após update)';
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'ok' => true,
            'message' => 'Cache limpo com sucesso. Use "Aquecer Sistema" ou reinicie o ERP para voltar à velocidade normal.',
            'cleared' => $cleared,
        ];
    }

    /**
     * Dispara aquecimento completo sem bloquear o worker HTTP atual.
     *
     * @return array{ok: bool, message: string, hits: int, failed: int, compiled?: int, routes_ok?: int, routes_total?: int}
     */
    public function aquecerSistema(): array
    {
        if ($this->launchBackgroundWarm()) {
            return [
                'ok' => true,
                'message' => 'Aquecimento iniciado em segundo plano (telas do menu + OPcache). Em cerca de 1 minuto as telas devem abrir mais rápido.',
                'hits' => 1,
                'failed' => 0,
            ];
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            /** @var ErpWarmService $warm */
            $warm = app(ErpWarmService::class);
            $result = $warm->warm();

            return [
                'ok' => $result['ok'],
                'message' => $result['message'],
                'hits' => $result['routes_ok'],
                'failed' => $result['routes_fail'],
                'compiled' => $result['compiled'],
                'routes_ok' => $result['routes_ok'],
                'routes_total' => $result['routes_total'],
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Falha ao aquecer: '.$e->getMessage(),
                'hits' => 0,
                'failed' => 1,
            ];
        }
    }

    protected function launchBackgroundWarm(): bool
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        if (! is_file($artisan)) {
            return false;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = 'cmd /c start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' unitec:warm -q';
                pclose(popen($cmd, 'r'));

                return true;
            }

            exec(escapeshellarg($php).' '.escapeshellarg($artisan).' unitec:warm -q > /dev/null 2>&1 &');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function infoSistema(): array
    {
        $opcache = 'Desligado';
        if (function_exists('opcache_get_status')) {
            try {
                $status = @opcache_get_status(false);
                if (is_array($status) && ($status['opcache_enabled'] ?? false)) {
                    $memory = $status['memory_usage']['used_memory'] ?? null;
                    $scripts = $status['opcache_statistics']['num_cached_scripts'] ?? null;
                    $parts = ['Ativo'];
                    if (is_int($scripts)) {
                        $parts[] = $scripts.' scripts';
                    }
                    if (is_int($memory)) {
                        $parts[] = $this->formatBytes($memory).' em uso';
                    }
                    $opcache = implode(' · ', $parts);
                }
            } catch (Throwable) {
                $opcache = 'Indisponível';
            }
        }

        $db = (string) config('database.default', '—');
        $dbName = (string) config("database.connections.{$db}.database", '—');

        return [
            ['label' => 'Versão do ERP', 'value' => ErpUpdateService::readInstalledVersion() ?: '—'],
            ['label' => 'Aplicação', 'value' => (string) config('unitec.app_name', config('app.name', '—'))],
            ['label' => 'Laravel', 'value' => app()->version()],
            ['label' => 'PHP', 'value' => PHP_VERSION.' ('.PHP_SAPI.')'],
            ['label' => 'OPcache', 'value' => $opcache],
            ['label' => 'Sistema', 'value' => PHP_OS_FAMILY.' · '.php_uname('n')],
            ['label' => 'URL', 'value' => (string) config('app.url', '—')],
            ['label' => 'Pasta', 'value' => base_path()],
            ['label' => 'Banco', 'value' => "{$db} / {$dbName}"],
            ['label' => 'Ambiente', 'value' => (string) config('app.env', '—')],
            ['label' => 'Memória PHP', 'value' => (string) ini_get('memory_limit')],
            ['label' => 'Timezone', 'value' => (string) config('app.timezone', date_default_timezone_get())],
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
