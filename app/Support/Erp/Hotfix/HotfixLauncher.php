<?php

namespace App\Support\Erp\Hotfix;

use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\License\LicencaSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Disparo do hotfix no login: nunca bloqueia nem derruba o login.
 * Roda `unitec:hotfix` em processo separado, no máximo uma vez a cada INTERVALO_SEGUNDOS.
 * A empresa do login só filtra o disparo; quem autoriza é o serviço, consultando todas as empresas.
 */
final class HotfixLauncher
{
    private const INTERVALO_SEGUNDOS = 300;

    public static function aposLogin(?string $cnpj, ?LicencaSnapshot $snapshot): void
    {
        try {
            $cnpj = preg_replace('/\D/', '', (string) $cnpj) ?? '';

            if (strlen($cnpj) !== 14 || $snapshot === null) {
                return;
            }

            HotfixEstado::lembrarPermissao($cnpj, $snapshot->modoAtualizacao);

            if (! $snapshot->permiteHotfix() || ! $snapshot->isAllowed()) {
                return;
            }

            $desde = HotfixEstado::segundosDesdeUltimaVerificacao();

            if ($desde !== null && $desde < self::INTERVALO_SEGUNDOS) {
                return;
            }

            if (HotfixEstado::lockOcupado()) {
                return;
            }

            self::disparar();
        } catch (\Throwable $e) {
            HotfixLog::line('Disparo', 'falhou: '.$e->getMessage());
        }
    }

    private static function disparar(): void
    {
        $appPath = base_path();
        $php = ErpUpdateProcessLauncher::resolvePhpBinary($appPath);
        $artisan = $appPath.DIRECTORY_SEPARATOR.'artisan';
        $log = storage_path('logs'.DIRECTORY_SEPARATOR.'erp-hotfix-run.log');
        File::ensureDirectoryExists(dirname($log));

        if (PHP_OS_FAMILY === 'Windows') {
            $batch = storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'erp-hotfix.bat');
            $quote = static fn (string $value): string => str_replace('"', '""', $value);
            File::ensureDirectoryExists(dirname($batch));
            File::put($batch, implode("\r\n", [
                '@echo off',
                'chcp 65001 >nul',
                'cd /d "'.$quote($appPath).'"',
                '"'.$quote($php).'" -d opcache.enable_cli=0 "'.$quote($artisan).'" unitec:hotfix >> "'.$quote($log).'" 2>&1',
            ])."\r\n");

            $handle = @popen('start "" /B cmd /C '.escapeshellarg($batch), 'r');

            if ($handle !== false) {
                pclose($handle);
                HotfixLog::line('Disparo', 'login');
            }

            return;
        }

        Process::path($appPath)
            ->timeout(null)
            ->start([$php, '-d', 'opcache.enable_cli=0', $artisan, 'unitec:hotfix']);
        HotfixLog::line('Disparo', 'login');
    }
}
