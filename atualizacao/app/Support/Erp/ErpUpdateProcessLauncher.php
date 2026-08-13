<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ErpUpdateProcessLauncher
{
    public static function launch(string $appPath, string $artisanCommand = 'unitec:apply-update'): bool
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
        self::log($logFile, 'Disparando '.$artisanCommand);
        self::log($logFile, 'PHP: '.$phpBinary);
        self::log($logFile, 'AppPath: '.$appPath);

        // Pipeline oficial no Windows: Unitec Atualizador.exe (para apply-update).
        if (PHP_OS_FAMILY === 'Windows' && str_starts_with(trim($artisanCommand), 'unitec:apply-update')) {
            if (self::launchViaUnitecUpdater($appPath, $logFile)) {
                return true;
            }
            self::log($logFile, 'Aviso: Unitec Atualizador indisponivel — fallback artisan.');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return self::launchViaWindowsBatch($appPath, $phpBinary, $artisan, $logFile, $artisanCommand)
                || self::launchViaProcess($appPath, $phpBinary, $artisan, $logFile, $artisanCommand);
        }

        return self::launchViaProcess($appPath, $phpBinary, $artisan, $logFile, $artisanCommand)
            || self::launchViaUnixShell($appPath, $phpBinary, $artisan, $logFile, $artisanCommand);
    }

    private static function launchViaUnitecUpdater(string $appPath, string $logFile): bool
    {
        $updater = $appPath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'Unitec Atualizador.exe';
        if (! is_file($updater)) {
            return false;
        }

        // Sem ZIP local, o Atualizador so limpa cache e derruba o PHP — cair no fallback artisan.
        $zipName = (string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip');
        $localZip = $appPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'updates'
            .DIRECTORY_SEPARATOR.$zipName;
        if (! is_file($localZip) || filesize($localZip) < 1024) {
            self::log($logFile, 'Unitec Atualizador presente, mas ZIP local ausente — fallback artisan.');

            return false;
        }

        if (self::isShellFunctionDisabled('popen')) {
            self::log($logFile, 'ERRO: popen desabilitado — nao foi possivel disparar Unitec Atualizador.exe.');

            return false;
        }

        // Batch evita o bug do cmd /C com aspas duplicadas no exe (espaco em "Unitec Atualizador.exe").
        $batchPath = storage_path('app/private/erp-update-updater-run.bat');
        File::ensureDirectoryExists(dirname($batchPath));
        $q = static fn (string $path): string => str_replace('"', '""', $path);
        $batch = implode("\r\n", [
            '@echo off',
            'chcp 65001 >nul',
            'start "" /B "'.$q($updater).'" --app "'.$q($appPath).'" --zip "'.$q($localZip).'" --quiet',
        ])."\r\n";
        File::put($batchPath, $batch);
        self::log($logFile, 'Batch Atualizador gerado: '.$batchPath);

        $handle = @popen('start "" /B cmd /C '.escapeshellarg($batchPath), 'r');
        if ($handle === false) {
            self::log($logFile, 'ERRO: nao foi possivel executar o batch do Unitec Atualizador.');

            return false;
        }

        pclose($handle);
        self::log($logFile, 'Atualizacao disparada via Unitec Atualizador.exe --zip '.$localZip);

        return true;
    }

    /**
     * Abre Unitec Atualizador.exe com janela (sem --quiet) para atualização manual.
     */
    public static function launchManualUpdater(string $appPath): bool
    {
        $appPath = rtrim($appPath, '\\/');
        $logFile = storage_path('logs/erp-update-spawn.log');
        File::ensureDirectoryExists(dirname($logFile));

        if (PHP_OS_FAMILY !== 'Windows') {
            self::log($logFile, 'ERRO: atualizacao manual via Atualizador so e suportada no Windows.');

            return false;
        }

        $updater = $appPath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'Unitec Atualizador.exe';
        if (! is_file($updater)) {
            self::log($logFile, 'ERRO: Unitec Atualizador.exe ausente em '.$updater);

            return false;
        }

        $zipName = (string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip');
        $localZip = $appPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'updates'
            .DIRECTORY_SEPARATOR.$zipName;
        if (! is_file($localZip) || filesize($localZip) < 1024) {
            self::log($logFile, 'ERRO: ZIP local ausente para atualizacao manual: '.$localZip);

            return false;
        }

        if (self::isShellFunctionDisabled('popen')) {
            self::log($logFile, 'ERRO: popen desabilitado — nao foi possivel disparar Unitec Atualizador.exe.');

            return false;
        }

        // Sem /B no start do exe e sem --quiet: janela visivel; o Atualizador encerra o PHP/servico.
        $batchPath = storage_path('app/private/erp-update-manual-run.bat');
        File::ensureDirectoryExists(dirname($batchPath));
        $q = static fn (string $path): string => str_replace('"', '""', $path);
        $batch = implode("\r\n", [
            '@echo off',
            'chcp 65001 >nul',
            'start "" "'.$q($updater).'" --app "'.$q($appPath).'" --zip "'.$q($localZip).'"',
        ])."\r\n";
        File::put($batchPath, $batch);
        self::log($logFile, 'Batch Atualizador MANUAL gerado: '.$batchPath);

        $handle = @popen('start "" /B cmd /C '.escapeshellarg($batchPath), 'r');
        if ($handle === false) {
            self::log($logFile, 'ERRO: nao foi possivel executar o batch do Atualizador manual.');

            return false;
        }

        pclose($handle);
        self::log($logFile, 'Atualizacao MANUAL disparada via Unitec Atualizador.exe --zip '.$localZip);

        return true;
    }

    public static function launchDownload(string $appPath, bool $force = false): bool
    {
        $command = 'unitec:download-update';
        if ($force) {
            $command .= ' --force';
        }

        return self::launch($appPath, $command);
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
        string $logFile,
        string $artisanCommand = 'unitec:apply-update'
    ): bool {
        try {
            $parts = preg_split('/\s+/', trim($artisanCommand)) ?: ['unitec:apply-update'];
            $args = array_merge(
                [$phpBinary, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', $artisan],
                $parts,
                ['--app-path='.$appPath]
            );

            $process = Process::path($appPath)
                ->timeout(null)
                ->env(self::inheritEnvironment())
                ->start($args);

            usleep(1_000_000);

            if ($process->running()) {
                self::log($logFile, 'Processo em execucao via Process::start ('.$artisanCommand.').');

                return true;
            }

            self::log(
                $logFile,
                'Process::start encerrou cedo (codigo '.($process->exitCode() ?? 'null').').'
            );

            return self::waitForUpdateStart($logFile, 4, $artisanCommand);
        } catch (\Throwable $exception) {
            self::log($logFile, 'Process::start falhou: '.$exception->getMessage());
        }

        return false;
    }

    private static function launchViaWindowsBatch(
        string $appPath,
        string $phpBinary,
        string $artisan,
        string $logFile,
        string $artisanCommand = 'unitec:apply-update'
    ): bool {
        if (self::isShellFunctionDisabled('popen')) {
            self::log($logFile, 'ERRO: popen desabilitado no PHP.');

            return false;
        }

        $isDownload = str_contains($artisanCommand, 'download-update');
        $batchPath = storage_path('app/private/'.($isDownload ? 'erp-update-download-run.bat' : 'erp-update-run.bat'));
        File::ensureDirectoryExists(dirname($batchPath));

        // OPcache desligado no processo de update — evita aplicar/finalizar com bytecode antigo.
        $batch = implode("\r\n", [
            '@echo off',
            'chcp 65001 >nul',
            'cd /d "'.str_replace('"', '""', $appPath).'"',
            'if exist "tools\php\opcache\" del /q "tools\php\opcache\*" >nul 2>&1',
            'if exist "bootstrap\cache\config.php" del /q "bootstrap\cache\config.php" >nul 2>&1',
            '"'.str_replace('"', '""', $phpBinary).'" -d opcache.enable=0 -d opcache.enable_cli=0 "'.str_replace('"', '""', $artisan).'" '.$artisanCommand.' --app-path="'.str_replace('"', '""', $appPath).'" >> "'.str_replace('"', '""', $logFile).'" 2>&1',
        ])."\r\n";

        File::put($batchPath, $batch);
        self::log($logFile, 'Batch gerado: '.$batchPath);

        $handle = @popen('start "" /B cmd /C '.escapeshellarg($batchPath), 'r');
        if ($handle === false) {
            self::log($logFile, 'ERRO: nao foi possivel executar o batch de atualizacao.');

            return false;
        }

        pclose($handle);

        return self::waitForUpdateStart($logFile, 8, $artisanCommand);
    }

    private static function launchViaUnixShell(
        string $appPath,
        string $phpBinary,
        string $artisan,
        string $logFile,
        string $artisanCommand = 'unitec:apply-update'
    ): bool {
        if (self::isShellFunctionDisabled('exec')) {
            self::log($logFile, 'ERRO: exec desabilitado no PHP.');

            return false;
        }

        $command = sprintf(
            '%s %s %s --app-path=%s >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisan),
            $artisanCommand,
            escapeshellarg($appPath),
            escapeshellarg($logFile)
        );

        self::log($logFile, 'Fallback Unix: '.$command);
        exec($command, $output, $exitCode);
        self::log($logFile, 'Fallback exec exit='.$exitCode);

        return self::waitForUpdateStart($logFile, 8, $artisanCommand);
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

    private static function waitForUpdateStart(string $logFile, int $attempts = 6, string $artisanCommand = 'unitec:apply-update'): bool
    {
        $isDownload = str_contains($artisanCommand, 'download-update');

        for ($i = 0; $i < $attempts; $i++) {
            if ($isDownload) {
                if (ErpUpdateService::isDownloadRunning() || ErpUpdateService::isLocalPackageReady()) {
                    return true;
                }
                $meta = ErpUpdateService::readPackageMeta();
                if (in_array((string) ($meta['download_state'] ?? ''), ['checking', 'downloading', 'ready', 'failed'], true)) {
                    return true;
                }
            } elseif (self::looksLikeUpdateStarted($logFile)) {
                return true;
            }

            usleep(500_000);
        }

        self::log($logFile, 'ERRO: processo de atualizacao nao confirmado apos espera.');

        return false;
    }

    private static function looksLikeUpdateStarted(string $logFile): bool
    {
        if (ErpUpdateService::isRunning()) {
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
