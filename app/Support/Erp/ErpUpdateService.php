<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class ErpUpdateService
{
    private const STATUS_FILE = 'app/private/erp-update-status.json';

    private const LOCK_FILE = 'app/private/erp-update.lock';

    /**
     * @return array<string, mixed>
     */
    public static function readStatus(): array
    {
        $path = storage_path(self::STATUS_FILE);

        if (! is_file($path)) {
            return [
                'state' => 'idle',
                'message' => 'Aguardando.',
                'percent' => 0,
            ];
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return [
                'state' => 'idle',
                'message' => 'Aguardando.',
                'percent' => 0,
            ];
        }

        return $data;
    }

    public static function isRunning(): bool
    {
        self::clearStaleLock();

        $status = self::readStatus();
        $state = (string) ($status['state'] ?? 'idle');

        if (! in_array($state, ['starting', 'downloading', 'extracting', 'applying', 'migrating', 'finalizing'], true)) {
            return false;
        }

        return is_file(storage_path(self::LOCK_FILE));
    }

    public static function clearStaleLock(int $maxAgeSeconds = 1800): void
    {
        $lockPath = storage_path(self::LOCK_FILE);
        $statusPath = storage_path(self::STATUS_FILE);

        if (is_file($lockPath)) {
            $age = time() - (int) filemtime($lockPath);
            if ($age > $maxAgeSeconds) {
                File::delete($lockPath);
            }
        }

        if (! is_file($statusPath)) {
            return;
        }

        $data = json_decode((string) file_get_contents($statusPath), true);
        if (! is_array($data)) {
            return;
        }

        $state = (string) ($data['state'] ?? 'idle');
        if (! in_array($state, ['starting', 'downloading', 'extracting', 'applying', 'migrating', 'finalizing'], true)) {
            return;
        }

        $updatedAt = strtotime((string) ($data['updated_at'] ?? ''));
        if ($updatedAt === false) {
            return;
        }

        if ((time() - $updatedAt) > $maxAgeSeconds) {
            self::writeStatus(
                'failed',
                'Atualização interrompida ou travada. Remova o lock e tente novamente.',
                0
            );
            File::delete($lockPath);
        }
    }

    public static function forceReset(): void
    {
        File::delete(storage_path(self::LOCK_FILE));
        self::writeStatus('idle', 'Aguardando.', 0);
    }

    public static function resetStatus(): void
    {
        self::writeStatus(
            'starting',
            'Preparando atualização',
            5,
            'Enviando comando para o servidor iniciar o processo',
            'php artisan unitec:apply-update'
        );
    }

    public function run(string $appPath): void
    {
        $lockPath = storage_path(self::LOCK_FILE);
        File::ensureDirectoryExists(dirname($lockPath));

        if (is_file($lockPath)) {
            $age = time() - (int) filemtime($lockPath);
            if ($age < 1800) {
                throw new RuntimeException('Já existe uma atualização em andamento.');
            }

            File::delete($lockPath);
        }

        File::put($lockPath, (string) time());

        self::writeStatus(
            'starting',
            'Processo de atualização iniciado',
            8,
            'Conectando ao servidor de download...',
            'php artisan unitec:apply-update'
        );

        $tempRoot = storage_path('app/private/erp-update-'.uniqid('', true));
        $zipPath = $tempRoot.DIRECTORY_SEPARATOR.'package.zip';
        $extractRoot = $tempRoot.DIRECTORY_SEPARATOR.'extract';

        try {
            File::ensureDirectoryExists($tempRoot);
            File::ensureDirectoryExists($extractRoot);

            $this->log($appPath, 'Iniciando atualizacao via PHP.');
            $this->ensureEmbeddedPhpConfiguration($appPath);

            $downloadUrl = $this->resolveUpdateDownloadUrl();

            self::writeStatus(
                'downloading',
                'Baixando pacote de atualização',
                15,
                $this->describeDownloadSource($downloadUrl),
                'HTTP GET → '.basename($downloadUrl)
            );
            $this->downloadPackage($zipPath, $downloadUrl);

            self::writeStatus(
                'extracting',
                'Extraindo arquivos do pacote',
                38,
                'Descompactando ZIP e validando estrutura (artisan, vendor/)',
                class_exists(ZipArchive::class) ? 'ZipArchive::extractTo' : 'Expand-Archive (PowerShell)'
            );
            $sourceRoot = $this->extractPackage($zipPath, $extractRoot);

            self::writeStatus(
                'applying',
                'Aplicando arquivos no sistema',
                58,
                'Preservando .env, storage/ e tools/ do cliente',
                'Cópia de arquivos + vendor/'
            );
            $this->applyPackage($sourceRoot, $appPath);
            $this->ensureEmbeddedPhpConfiguration($appPath);

            self::writeStatus(
                'migrating',
                'Atualizando banco de dados',
                82,
                'Executando migrations pendentes',
                'php artisan migrate --force'
            );
            $this->runMigrations($appPath);

            self::writeStatus(
                'finalizing',
                'Finalizando configuração',
                92,
                'Limpando views e recriando cache de configuração',
                'php artisan view:clear && config:cache'
            );
            $this->finalizeCaches($appPath);

            $this->log($appPath, 'Atualizacao concluida com sucesso.');

            self::writeStatus(
                'completed',
                'Atualização concluída',
                100,
                'Recarregando a página em instantes...',
                null
            );
        } catch (\Throwable $exception) {
            $this->log($appPath, 'ERRO: '.$exception->getMessage());
            self::writeStatus('failed', $exception->getMessage(), 0);

            throw $exception;
        } finally {
            File::delete($lockPath);
            File::deleteDirectory($tempRoot);
        }
    }

    private function resolveUpdateDownloadUrl(): string
    {
        $url = trim((string) config('unitec.update_download_url', ''));

        if ($url === '') {
            $url = rtrim((string) config('unitec.pagamento_url'), '/')
                .'/updates/'
                .urlencode((string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip'));
        }

        return $url;
    }

    private function downloadPackage(string $destination, ?string $url = null): void
    {
        $url ??= $this->resolveUpdateDownloadUrl();
        $sourceDetail = $this->describeDownloadSource($url);
        $path = parse_url($url, PHP_URL_PATH);
        $command = 'HTTP GET → '.basename(is_string($path) && $path !== '' ? $path : 'Unitec-ERP-Update.zip');
        $lastWrite = 0;

        $response = Http::timeout(900)
            ->withOptions([
                'sink' => $destination,
                'verify' => $this->resolveSslVerifyPath(),
                'progress' => function ($downloadTotal, $downloadedBytes) use ($destination, $sourceDetail, $command, &$lastWrite): void {
                    $now = time();
                    $downloaded = max(0, (int) $downloadedBytes);

                    if ($downloaded <= 0 && is_file($destination)) {
                        $downloaded = (int) filesize($destination);
                    }

                    if ($now - $lastWrite < 2 && $downloaded > 0) {
                        return;
                    }

                    $lastWrite = $now;
                    $total = max(0, (int) $downloadTotal);
                    $stepPercent = 0;

                    if ($total > 0 && $downloaded > 0) {
                        $stepPercent = min(99, (int) floor(($downloaded / $total) * 100));
                    } elseif ($downloaded > 0) {
                        $estimatedTotal = 250 * 1024 * 1024;
                        $stepPercent = min(95, (int) floor(($downloaded / $estimatedTotal) * 100));
                    }

                    $globalPercent = max(9, 8 + (int) floor($stepPercent * 30 / 100));

                    self::writeStatus(
                        'downloading',
                        'Baixando pacote de atualização',
                        $globalPercent,
                        self::formatDownloadDetail($downloaded, $total, $sourceDetail),
                        $command,
                        $downloaded,
                        $total > 0 ? $total : null
                    );
                },
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao baixar o pacote (HTTP '.$response->status().').');
        }

        if (! is_file($destination) || filesize($destination) < 1024) {
            throw new RuntimeException('Pacote baixado parece inválido ou vazio.');
        }

        $finalSize = (int) filesize($destination);

        self::writeStatus(
            'downloading',
            'Download concluído',
            38,
            self::formatDownloadDetail($finalSize, $finalSize, $sourceDetail),
            $command,
            $finalSize,
            $finalSize
        );
    }

    /**
     * @return string|bool
     */
    private function resolveSslVerifyPath(): string|bool
    {
        foreach ($this->sslCaCandidatePaths(base_path()) as $path) {
            if (is_file($path) && filesize($path) > 1024) {
                return $path;
            }
        }

        $curlCa = trim((string) ini_get('curl.cainfo'));
        if ($curlCa !== '' && is_file($curlCa)) {
            return $curlCa;
        }

        $opensslCa = trim((string) ini_get('openssl.cafile'));
        if ($opensslCa !== '' && is_file($opensslCa)) {
            return $opensslCa;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function sslCaCandidatePaths(string $appPath): array
    {
        return [
            $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'cacert.pem',
            $appPath.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'cacert.pem',
            $appPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'guzzlehttp'.DIRECTORY_SEPARATOR.'guzzle'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'cacert.pem',
        ];
    }

    private function ensureEmbeddedPhpConfiguration(string $appPath): void
    {
        $this->ensurePhpSslCaBundle($appPath);
        $this->ensurePhpExtensionInIni($appPath, 'zip');
    }

    private function ensurePhpExtensionInIni(string $appPath, string $extension): void
    {
        $phpDir = $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php';
        if (! is_dir($phpDir)) {
            return;
        }

        $iniPath = $phpDir.DIRECTORY_SEPARATOR.'php.ini';
        if (! is_file($iniPath)) {
            $devIni = $phpDir.DIRECTORY_SEPARATOR.'php.ini-development';
            if (is_file($devIni)) {
                File::copy($devIni, $iniPath);
            } else {
                return;
            }
        }

        $content = (string) file_get_contents($iniPath);
        $replacement = 'extension='.$extension;

        if (preg_match('/^\s*'.preg_quote($extension, '/').'\s*=/m', $content)) {
            $content = (string) preg_replace('/^\s*;?\s*extension\s*=\s*'.preg_quote($extension, '/').'.*$/m', $replacement, $content);
        } else {
            $content .= PHP_EOL.$replacement.PHP_EOL;
        }

        file_put_contents($iniPath, $content);
    }

    private function ensurePhpSslCaBundle(string $appPath): void
    {
        $phpDir = $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php';
        if (! is_dir($phpDir)) {
            return;
        }

        $targetDir = $phpDir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl';
        $targetPath = $targetDir.DIRECTORY_SEPARATOR.'cacert.pem';

        if (! is_dir($targetDir)) {
            File::ensureDirectoryExists($targetDir);
        }

        if (! is_file($targetPath) || filesize($targetPath) < 1024) {
            foreach ($this->sslCaCandidatePaths($appPath) as $source) {
                if (is_file($source) && filesize($source) > 1024) {
                    File::copy($source, $targetPath);

                    break;
                }
            }
        }

        if (! is_file($targetPath)) {
            return;
        }

        $iniPath = $phpDir.DIRECTORY_SEPARATOR.'php.ini';
        if (! is_file($iniPath)) {
            $devIni = $phpDir.DIRECTORY_SEPARATOR.'php.ini-development';
            if (is_file($devIni)) {
                File::copy($devIni, $iniPath);
            } else {
                return;
            }
        }

        $content = (string) file_get_contents($iniPath);
        $iniCaPath = str_replace('\\', '/', $targetPath);
        $quotedPath = '"'.$iniCaPath.'"';

        foreach (['curl.cainfo', 'openssl.cafile'] as $key) {
            $replacement = $key.' = '.$quotedPath;

            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/m', $content)) {
                $content = (string) preg_replace('/^\s*'.preg_quote($key, '/').'\s*=.*$/m', $replacement, $content);
            } elseif (preg_match('/^\s*;\s*'.preg_quote($key, '/').'\s*=/m', $content)) {
                $content = (string) preg_replace('/^\s*;\s*'.preg_quote($key, '/').'\s*=.*$/m', $replacement, $content);
            } else {
                $content .= PHP_EOL.$replacement.PHP_EOL;
            }
        }

        file_put_contents($iniPath, $content);
    }

    private function extractPackage(string $zipPath, string $extractRoot): string
    {
        if (class_exists(ZipArchive::class)) {
            return $this->extractPackageViaZipArchive($zipPath, $extractRoot);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->extractPackageViaPowerShell($zipPath, $extractRoot);
        }

        throw new RuntimeException(
            'Extensão PHP zip não habilitada. Edite tools/php/php.ini, adicione extension=zip e tente novamente.'
        );
    }

    private function extractPackageViaZipArchive(string $zipPath, string $extractRoot): string
    {
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw new RuntimeException('Não foi possível abrir o ZIP de atualização.');
        }

        if (! $zip->extractTo($extractRoot)) {
            $zip->close();
            throw new RuntimeException('Falha ao extrair o pacote de atualização.');
        }

        $zip->close();

        return $this->resolveSourceRoot($extractRoot);
    }

    private function extractPackageViaPowerShell(string $zipPath, string $extractRoot): string
    {
        File::ensureDirectoryExists($extractRoot);

        $escapedZip = str_replace("'", "''", $zipPath);
        $escapedDest = str_replace("'", "''", $extractRoot);
        $command = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -LiteralPath \'%s\' -DestinationPath \'%s\' -Force"',
            $escapedZip,
            $escapedDest
        );

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Falha ao extrair o pacote (PowerShell Expand-Archive). Habilite extension=zip em tools/php/php.ini e tente novamente.'
            );
        }

        return $this->resolveSourceRoot($extractRoot);
    }

    private function resolveSourceRoot(string $extractRoot): string
    {
        $nested = $extractRoot.DIRECTORY_SEPARATOR.'unitec-erp-web';

        if (is_file($nested.DIRECTORY_SEPARATOR.'artisan')) {
            return $nested;
        }

        if (is_file($extractRoot.DIRECTORY_SEPARATOR.'artisan')) {
            return $extractRoot;
        }

        throw new RuntimeException('Pacote inválido: artisan não encontrado no ZIP.');
    }

    private function applyPackage(string $sourceRoot, string $targetRoot): void
    {
        if (! is_file($sourceRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
            throw new RuntimeException('Pacote inválido: vendor/autoload.php ausente.');
        }

        $excludeDirs = [
            'storage',
            'tools',
            'node_modules',
            '.git',
            'dist',
            '.cursor',
            '.idea',
            '.vscode',
            '.codex',
            '.phpunit.cache',
            'vendor',
            'public'.DIRECTORY_SEPARATOR.'storage',
        ];

        $excludeFiles = ['.env', '.env.backup', '.env.production'];

        $this->copyDirectory($sourceRoot, $targetRoot, $excludeDirs, $excludeFiles);

        $this->copyDirectory(
            $sourceRoot.DIRECTORY_SEPARATOR.'vendor',
            $targetRoot.DIRECTORY_SEPARATOR.'vendor',
            [],
            []
        );
    }

    /**
     * @param  list<string>  $excludeDirs
     * @param  list<string>  $excludeFiles
     */
    private function copyDirectory(string $source, string $target, array $excludeDirs, array $excludeFiles): void
    {
        if (! is_dir($source)) {
            return;
        }

        File::ensureDirectoryExists($target);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;
        $lastHeartbeat = time();

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $relative = str_replace('\\', '/', $relative);

            if ($relative === false || $relative === '') {
                continue;
            }

            if ($this->shouldExcludeRelativePath($relative, $excludeDirs, $excludeFiles)) {
                continue;
            }

            $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($item->getPathname(), $destination);
            $copied++;

            if ((time() - $lastHeartbeat) >= 4) {
                $percent = min(78, 58 + (int) floor($copied / 250));
                ErpUpdateService::writeStatus(
                    'applying',
                    'Copiando arquivos do pacote',
                    $percent,
                    number_format($copied, 0, ',', '.').' arquivos copiados',
                    'Atualização de app/ e vendor/'
                );
                $lastHeartbeat = time();
            }
        }
    }

    /**
     * @param  list<string>  $excludeDirs
     * @param  list<string>  $excludeFiles
     */
    private function shouldExcludeRelativePath(string $relative, array $excludeDirs, array $excludeFiles): bool
    {
        $normalized = str_replace('\\', '/', $relative);

        foreach ($excludeFiles as $fileName) {
            if (basename($normalized) === $fileName) {
                return true;
            }
        }

        foreach ($excludeDirs as $dir) {
            $dir = str_replace('\\', '/', $dir);
            if ($normalized === $dir || str_starts_with($normalized, $dir.'/')) {
                return true;
            }
        }

        return false;
    }

    private function runMigrations(string $appPath): void
    {
        $previous = getcwd();
        chdir($appPath);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
        }
    }

    private function finalizeCaches(string $appPath): void
    {
        $previous = getcwd();
        chdir($appPath);

        try {
            Artisan::call('view:clear');
            Artisan::call('config:cache');
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
        }
    }

    private function log(string $appPath, string $message): void
    {
        $logFile = $appPath.DIRECTORY_SEPARATOR.'instalacao.log';
        $line = '['.now()->format('H:i:s').'] '.$message.PHP_EOL;

        try {
            File::append($logFile, $line);
        } catch (\Throwable) {
            // ignore
        }
    }

    private function describeDownloadSource(string $url): string
    {
        $zipName = (string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip');
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $zipName.' ← '.$host;
        }

        return $zipName;
    }

    /**
     * @return array{step: int, label: string}
     */
    private static function stepInfo(string $state): array
    {
        return match ($state) {
            'starting' => ['step' => 1, 'label' => 'Preparar'],
            'downloading' => ['step' => 2, 'label' => 'Baixar pacote'],
            'extracting' => ['step' => 3, 'label' => 'Extrair ZIP'],
            'applying' => ['step' => 4, 'label' => 'Aplicar arquivos'],
            'migrating' => ['step' => 5, 'label' => 'Banco de dados'],
            'finalizing' => ['step' => 6, 'label' => 'Finalizar'],
            'completed' => ['step' => 7, 'label' => 'Concluído'],
            'failed' => ['step' => 0, 'label' => 'Erro'],
            default => ['step' => 0, 'label' => 'Aguardando'],
        };
    }

    private static function formatDownloadDetail(int $downloaded, int $total, string $source): string
    {
        $downloadedMb = number_format($downloaded / 1024 / 1024, 1, ',', '.');

        if ($total > 0) {
            $totalMb = number_format($total / 1024 / 1024, 1, ',', '.');
            $percent = min(100, (int) floor(($downloaded / $total) * 100));

            return $source.' · '.$downloadedMb.' / '.$totalMb.' MB ('.$percent.'%)';
        }

        return $source.' · '.$downloadedMb.' MB baixados';
    }

    /**
     * @return array<string, int>
     */
    private static function stepPercentRanges(): array
    {
        return [
            'starting' => [0, 8],
            'downloading' => [8, 38],
            'extracting' => [38, 58],
            'applying' => [58, 82],
            'migrating' => [82, 92],
            'finalizing' => [92, 100],
            'completed' => [100, 100],
        ];
    }

    /**
     * @return list<string>
     */
    private static function stepOrder(): array
    {
        return [
            'starting',
            'downloading',
            'extracting',
            'applying',
            'migrating',
            'finalizing',
            'completed',
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function computeStepProgress(string $state, int $globalPercent): array
    {
        $ranges = self::stepPercentRanges();
        $order = self::stepOrder();
        $progress = [];

        if ($state === 'completed') {
            foreach ($order as $step) {
                $progress[$step] = 100;
            }

            return $progress;
        }

        $activeIndex = array_search($state, $order, true);
        if ($activeIndex === false) {
            foreach ($order as $step) {
                $progress[$step] = 0;
            }

            return $progress;
        }

        foreach ($order as $index => $step) {
            if ($index < $activeIndex) {
                $progress[$step] = 100;

                continue;
            }

            if ($index > $activeIndex) {
                $progress[$step] = 0;

                continue;
            }

            [$min, $max] = $ranges[$step] ?? [0, 100];
            $span = $max - $min;

            if ($span <= 0) {
                $progress[$step] = 100;

                continue;
            }

            $progress[$step] = max(0, min(100, (int) round((($globalPercent - $min) / $span) * 100)));
        }

        return $progress;
    }

    /**
     * @param  'starting'|'downloading'|'extracting'|'applying'|'migrating'|'finalizing'|'completed'|'failed'|'idle'  $state
     */
    public static function writeStatus(
        string $state,
        string $message,
        int $percent,
        ?string $detail = null,
        ?string $command = null,
        ?int $downloadBytes = null,
        ?int $downloadTotal = null
    ): void {
        $path = storage_path(self::STATUS_FILE);
        File::ensureDirectoryExists(dirname($path));

        $step = self::stepInfo($state);
        $normalizedPercent = max(0, min(100, $percent));

        $payload = [
            'state' => $state,
            'message' => $message,
            'detail' => $detail,
            'command' => $command,
            'step' => $step['step'],
            'step_label' => $step['label'],
            'percent' => $normalizedPercent,
            'step_progress' => self::computeStepProgress($state, $normalizedPercent),
            'updated_at' => now()->toIso8601String(),
        ];

        if ($downloadBytes !== null) {
            $payload['download_bytes'] = $downloadBytes;
        }

        if ($downloadTotal !== null && $downloadTotal > 0) {
            $payload['download_total'] = $downloadTotal;
        }

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
