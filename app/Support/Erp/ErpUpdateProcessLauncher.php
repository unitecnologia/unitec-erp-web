<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ErpUpdateProcessLauncher
{
    public static function launch(string $appPath): bool
    {
        $appPath = rtrim($appPath, '\\/');
        $phpBinary = self::resolvePhpBinary($appPath);
        $artisan = $appPath.DIRECTORY_SEPARATOR.'artisan';
        $logFile = storage_path('logs/erp-update-spawn.log');

        if (! is_file($artisan)) {
            self::log($logFile, 'ERRO: artisan nao encontrado em '.$artisan);

            return false;
        }

        if (! self::isUsablePhpBinary($phpBinary)) {
            self::log($logFile, 'ERRO: PHP invalido: '.$phpBinary);

            return false;
        }

        File::ensureDirectoryExists(dirname($logFile));
        self::log($logFile, 'Disparando unitec:apply-update');
        self::log($logFile, 'PHP: '.$phpBinary);
        self::log($logFile, 'AppPath: '.$appPath);

        if (PHP_OS_FAMILY === 'Windows') {
            return self::launchViaWindowsBatch($appPath, $phpBinary, $artisan, $logFile)
                || self::launchViaProcess($appPath, $phpBinary, $artisan, $logFile);
        }

        return self::launchViaProcess($appPath, $phpBinary, $artisan, $logFile)
            || self::launchViaUnixShell($appPath, $phpBinary, $artisan, $logFile);
    }

    public static function resolvePhpBinary(string $appPath): string
    {
        $embedded = self::findEmbeddedPhpExecutable($appPath);
        if ($embedded !== null) {
            return $embedded;
        }

        $binary = PHP_BINARY ?: '';
        if (self::isUsablePhpBinary($binary)) {
            return $binary;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $where = trim((string) shell_exec('where php 2>NUL'));
            $first = strtok($where, "\r\n");
            if (is_string($first) && self::isUsablePhpBinary($first)) {
                return $first;
            }
        }

        return 'php';
    }

    private static function launchViaProcess(
        string $appPath,
        string $phpBinary,
        string $artisan,
        string $logFile
    ): bool {
        try {
            $process = Process::path($appPath)
                ->timeout(null)
                ->env(self::inheritEnvironment())
                ->start([
                    $phpBinary,
                    $artisan,
                    'unitec:apply-update',
                    '--app-path='.$appPath,
                ]);

            usleep(1_000_000);

            if ($process->running()) {
                self::log($logFile, 'Processo em execucao via Process::start.');

                return true;
            }

            self::log(
                $logFile,
                'Process::start encerrou cedo (codigo '.($process->exitCode() ?? 'null').').'
            );

            return self::waitForUpdateStart($logFile, 4);
        } catch (\Throwable $exception) {
            self::log($logFile, 'Process::start falhou: '.$exception->getMessage());
        }

        return false;
    }

    private static function launchViaWindowsBatch(
        string $appPath,
        string $phpBinary,
        string $artisan,
        string $logFile
    ): bool {
        if (self::isShellFunctionDisabled('popen')) {
            self::log($logFile, 'ERRO: popen desabilitado no PHP.');

            return false;
        }

        $batchPath = storage_path('app/private/erp-update-run.bat');
        File::ensureDirectoryExists(dirname($batchPath));

        $batch = implode("\r\n", [
            '@echo off',
            'chcp 65001 >nul',
            'cd /d "'.str_replace('"', '""', $appPath).'"',
            '"'.str_replace('"', '""', $phpBinary).'" "'.str_replace('"', '""', $artisan).'" unitec:apply-update --app-path="'.str_replace('"', '""', $appPath).'" >> "'.str_replace('"', '""', $logFile).'" 2>&1',
        ])."\r\n";

        File::put($batchPath, $batch);
        self::log($logFile, 'Batch gerado: '.$batchPath);

        $handle = @popen('start "" /B cmd /C '.escapeshellarg($batchPath), 'r');
        if ($handle === false) {
            self::log($logFile, 'ERRO: nao foi possivel executar o batch de atualizacao.');

            return false;
        }

        pclose($handle);

        return self::waitForUpdateStart($logFile, 8);
    }

    private static function launchViaUnixShell(
        string $appPath,
        string $phpBinary,
        string $artisan,
        string $logFile
    ): bool {
        if (self::isShellFunctionDisabled('exec')) {
            self::log($logFile, 'ERRO: exec desabilitado no PHP.');

            return false;
        }

        $command = sprintf(
            '%s %s unitec:apply-update --app-path=%s >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            escapeshellarg($appPath),
            escapeshellarg($logFile)
        );

        self::log($logFile, 'Fallback Unix: '.$command);
        exec($command, $output, $exitCode);
        self::log($logFile, 'Fallback exec exit='.$exitCode);

        return self::waitForUpdateStart($logFile, 8);
    }

    private static function findEmbeddedPhpExecutable(string $appPath): ?string
    {
        $candidates = [
            $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'php.exe',
        ];

        $phpRoot = $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php';
        if (is_dir($phpRoot)) {
            foreach (scandir($phpRoot) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $candidate = $phpRoot.DIRECTORY_SEPARATOR.$entry.DIRECTORY_SEPARATOR.'php.exe';
                if (is_file($candidate)) {
                    $candidates[] = $candidate;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (self::isUsablePhpBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function isUsablePhpBinary(string $path): bool
    {
        if ($path === '' || $path === 'php') {
            return $path === 'php';
        }

        if (! is_file($path)) {
            return false;
        }

        $lower = strtolower($path);

        return ! str_contains($lower, 'php-cgi')
            && ! str_contains($lower, 'php-fpm');
    }

    /**
     * @return array<string, string>
     */
    private static function inheritEnvironment(): array
    {
        $env = [];
        $keys = ['PATH', 'PATHEXT', 'SystemRoot', 'TEMP', 'TMP', 'APP_ENV', 'COMPUTERNAME'];

        foreach ($keys as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        return $env;
    }

    private static function waitForUpdateStart(string $logFile, int $attempts = 6): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if (self::looksLikeUpdateStarted($logFile)) {
                return true;
            }

            usleep(500_000);
        }

        self::log($logFile, 'ERRO: processo de atualizacao nao confirmado apos espera.');

        return false;
    }

    private static function looksLikeUpdateStarted(string $logFile): bool
    {
        if (is_file(storage_path('app/private/erp-update.lock'))) {
            return true;
        }

        $status = ErpUpdateService::readStatus();
        $state = (string) ($status['state'] ?? 'idle');
        $percent = (int) ($status['percent'] ?? 0);

        if (in_array($state, ['downloading', 'extracting', 'applying', 'migrating', 'finalizing', 'completed', 'failed'], true)) {
            return true;
        }

        if ($state === 'starting' && $percent > 5) {
            return true;
        }

        if (! is_file($logFile)) {
            return false;
        }

        $tail = (string) file_get_contents($logFile);

        return str_contains($tail, 'Iniciando atualizacao via PHP.')
            || str_contains($tail, 'Processo em execucao');
    }

    private static function isShellFunctionDisabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array($function, $disabled, true);
    }

    private static function log(string $logFile, string $message): void
    {
        $line = '['.now()->format('Y-m-d H:i:s').'] '.$message.PHP_EOL;

        try {
            File::append($logFile, $line);
        } catch (\Throwable) {
            // ignore
        }
    }
}
