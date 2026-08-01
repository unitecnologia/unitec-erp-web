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

    private const DOWNLOAD_LOCK_FILE = 'app/private/erp-update-download.lock';

    private const UPDATES_DIR = 'app/private/updates';

    private const PACKAGE_ZIP = 'Unitec-ERP-Update.zip';

    private const PACKAGE_META = 'package.json';

    /**
     * @return array<string, mixed>
     */
    public static function readStatus(): array
    {
        $path = storage_path(self::STATUS_FILE);

        if (! is_file($path)) {
            return array_merge(self::defaultStatus(), self::packageStatusPayload());
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return array_merge(self::defaultStatus(), self::packageStatusPayload());
        }

        // Pacote local (download em background) deve prevalecer sobre o status de instalação.
        return array_merge(self::defaultStatus(), $data, self::packageStatusPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultStatus(): array
    {
        return [
            'state' => 'idle',
            'message' => 'Aguardando.',
            'percent' => 0,
        ];
    }

    public static function localPackagePath(): string
    {
        return storage_path(self::UPDATES_DIR.'/'.self::PACKAGE_ZIP);
    }

    public static function localPackageMetaPath(): string
    {
        return storage_path(self::UPDATES_DIR.'/'.self::PACKAGE_META);
    }

    public static function isLocalPackageReady(): bool
    {
        $path = self::localPackagePath();
        if (! is_file($path) || filesize($path) < 1_000_000) {
            return false;
        }

        $meta = self::readPackageMeta();

        return (bool) ($meta['package_ready'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function readPackageMeta(): array
    {
        $path = self::localPackageMetaPath();
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function writePackageMeta(array $meta): void
    {
        File::ensureDirectoryExists(dirname(self::localPackageMetaPath()));
        $current = self::readPackageMeta();
        $payload = array_merge($current, $meta, [
            'updated_at' => now()->toIso8601String(),
        ]);
        File::put(
            self::localPackageMetaPath(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function packageStatusPayload(): array
    {
        $meta = self::readPackageMeta();
        $path = self::localPackagePath();
        $ready = is_file($path) && filesize($path) >= 1_000_000 && (bool) ($meta['package_ready'] ?? false);
        $localVersion = (string) config('unitec.versao', '');
        $packageVersion = (string) ($meta['package_version'] ?? $meta['remote_version'] ?? '');
        $remoteVersion = (string) ($meta['remote_version'] ?? '');
        $effectiveRemote = $packageVersion !== '' ? $packageVersion : $remoteVersion;

        return [
            'local_version' => $localVersion,
            'remote_version' => $effectiveRemote !== '' ? $effectiveRemote : null,
            'update_available' => $ready && $effectiveRemote !== '' && version_compare($effectiveRemote, $localVersion, '>'),
            'package_ready' => $ready,
            'package_path' => $ready ? $path : null,
            'package_bytes' => $ready ? (int) filesize($path) : (int) ($meta['package_bytes'] ?? 0),
            'package_downloaded_at' => $meta['downloaded_at'] ?? null,
            'last_check_at' => $meta['last_check_at'] ?? null,
            'download_state' => (string) ($meta['download_state'] ?? 'idle'),
            'download_running' => self::isDownloadRunning(),
            'check_message' => (string) ($meta['check_message'] ?? ''),
        ];
    }

    public static function shouldAutoCheckForUpdates(int $maxAgeHours = 24): bool
    {
        $meta = self::readPackageMeta();
        $last = strtotime((string) ($meta['last_check_at'] ?? ''));
        if ($last === false) {
            return true;
        }

        return (time() - $last) >= ($maxAgeHours * 3600);
    }

    public static function isDownloadRunning(): bool
    {
        $lock = storage_path(self::DOWNLOAD_LOCK_FILE);
        if (! is_file($lock)) {
            $meta = self::readPackageMeta();

            return (($meta['download_state'] ?? '') === 'downloading');
        }

        $age = time() - (int) filemtime($lock);
        if ($age > 3600) {
            File::delete($lock);

            return false;
        }

        return true;
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

    public static function ensureFrameworkStorageDirectories(): void
    {
        foreach ([
            'framework/sessions',
            'framework/cache',
            'framework/cache/data',
            'framework/views',
            'framework/testing',
            'logs',
            'app/private',
            self::UPDATES_DIR,
        ] as $relative) {
            File::ensureDirectoryExists(storage_path($relative));
        }
    }

    public static function resetStatus(): void
    {
        self::writeStatus(
            'starting',
            'Preparando atualização',
            5,
            self::isLocalPackageReady()
                ? 'Usando pacote já baixado em storage/app/private/updates'
                : 'Enviando comando para o servidor iniciar o processo',
            'php artisan unitec:apply-update'
        );
    }

    /**
     * Verifica a nuvem 1x e baixa o ZIP para storage/app/private/updates (sem aplicar).
     *
     * @return array{downloaded: bool, message: string, remote_version: ?string}
     */
    public function downloadPendingUpdate(string $appPath, bool $force = false): array
    {
        $lockPath = storage_path(self::DOWNLOAD_LOCK_FILE);
        File::ensureDirectoryExists(dirname($lockPath));

        if (is_file($lockPath)) {
            $age = time() - (int) filemtime($lockPath);
            if ($age < 3600) {
                return [
                    'downloaded' => false,
                    'message' => 'Download de atualização já em andamento.',
                    'remote_version' => self::readPackageMeta()['remote_version'] ?? null,
                ];
            }
            File::delete($lockPath);
        }

        File::put($lockPath, (string) time());
        self::ensureFrameworkStorageDirectories();

        try {
            $localVersion = (string) config('unitec.versao', '0');
            $downloadUrl = $this->resolveUpdateDownloadUrl();
            $remoteVersion = $this->resolveRemoteVersion($downloadUrl);

            self::writePackageMeta([
                'download_state' => 'checking',
                'last_check_at' => now()->toIso8601String(),
                'local_version' => $localVersion,
                'remote_version' => $remoteVersion,
                'check_message' => 'Verificando atualizações...',
                'package_ready' => self::isLocalPackageReady(),
            ]);

            if ($remoteVersion !== null && version_compare($remoteVersion, $localVersion, '<=')) {
                if (! $force && self::isLocalPackageReady()) {
                    // Pacote antigo pode sobrar — limpa se versão remota já instalada.
                    $this->clearLocalPackage(keepMeta: true);
                }

                self::writePackageMeta([
                    'download_state' => 'idle',
                    'package_ready' => false,
                    'check_message' => 'Sistema já está na versão '.$localVersion.'.',
                    'remote_version' => $remoteVersion,
                    'last_check_at' => now()->toIso8601String(),
                ]);

                return [
                    'downloaded' => false,
                    'message' => 'Nenhuma atualização pendente (versão '.$localVersion.').',
                    'remote_version' => $remoteVersion,
                ];
            }

            if (! $force && self::isLocalPackageReady()) {
                $meta = self::readPackageMeta();
                $readyVersion = (string) ($meta['package_version'] ?? '');
                if ($remoteVersion !== null && $readyVersion !== '' && $readyVersion === $remoteVersion) {
                    self::writePackageMeta([
                        'download_state' => 'ready',
                        'check_message' => 'Pacote '.$remoteVersion.' já baixado. Pode instalar.',
                        'remote_version' => $remoteVersion,
                        'last_check_at' => now()->toIso8601String(),
                    ]);

                    return [
                        'downloaded' => false,
                        'message' => 'Pacote já está pronto para instalar ('.$remoteVersion.').',
                        'remote_version' => $remoteVersion,
                    ];
                }
            }

            $target = self::localPackagePath();
            $partial = $target.'.partial';
            File::ensureDirectoryExists(dirname($target));
            if (is_file($partial)) {
                File::delete($partial);
            }

            self::writePackageMeta([
                'download_state' => 'downloading',
                'package_ready' => false,
                'package_version' => null,
                'check_message' => 'Baixando pacote'.($remoteVersion ? ' '.$remoteVersion : '').'...',
                'remote_version' => $remoteVersion,
                'last_check_at' => now()->toIso8601String(),
            ]);

            $this->downloadPackage($partial, $downloadUrl);

            if (! is_file($partial) || filesize($partial) < 1_000_000) {
                throw new RuntimeException('Download incompleto do pacote de atualização.');
            }

            if (is_file($target)) {
                File::delete($target);
            }
            File::move($partial, $target);

            $bytes = (int) filesize($target);
            self::writePackageMeta([
                'download_state' => 'ready',
                'package_ready' => true,
                'package_bytes' => $bytes,
                'package_version' => $remoteVersion,
                'downloaded_at' => now()->toIso8601String(),
                'remote_version' => $remoteVersion,
                'local_version' => $localVersion,
                'check_message' => 'Pacote pronto para instalar'.($remoteVersion ? ' ('.$remoteVersion.')' : '').'.',
                'last_check_at' => now()->toIso8601String(),
            ]);

            $this->log($appPath, 'Pacote de atualizacao baixado: '.$target.' ('.$bytes.' bytes)');

            return [
                'downloaded' => true,
                'message' => 'Pacote baixado com sucesso'.($remoteVersion ? ' ('.$remoteVersion.')' : '').'.',
                'remote_version' => $remoteVersion,
            ];
        } catch (\Throwable $exception) {
            self::writePackageMeta([
                'download_state' => 'failed',
                'package_ready' => self::isLocalPackageReady(),
                'check_message' => $exception->getMessage(),
                'last_check_at' => now()->toIso8601String(),
            ]);
            $this->log($appPath, 'ERRO download update: '.$exception->getMessage());

            throw $exception;
        } finally {
            File::delete($lockPath);
            $partial = self::localPackagePath().'.partial';
            if (is_file($partial)) {
                File::delete($partial);
            }
        }
    }

    public function clearLocalPackage(bool $keepMeta = false): void
    {
        $path = self::localPackagePath();
        if (is_file($path)) {
            File::delete($path);
        }
        $partial = $path.'.partial';
        if (is_file($partial)) {
            File::delete($partial);
        }

        if ($keepMeta) {
            self::writePackageMeta([
                'package_ready' => false,
                'package_bytes' => 0,
                'package_version' => null,
                'download_state' => 'idle',
            ]);
        }
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
        self::ensureFrameworkStorageDirectories();

        $useLocal = self::isLocalPackageReady();

        self::writeStatus(
            'starting',
            'Processo de atualização iniciado',
            8,
            $useLocal
                ? 'Instalando pacote local (sem baixar novamente)'
                : 'Pacote local ausente — baixando da nuvem...',
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

            if ($useLocal) {
                $localZip = self::localPackagePath();
                File::copy($localZip, $zipPath);
                self::writeStatus(
                    'extracting',
                    'Extraindo pacote já baixado',
                    40,
                    'ZIP local em storage/app/private/updates',
                    class_exists(ZipArchive::class) ? 'ZipArchive::extractTo' : 'Expand-Archive (PowerShell)'
                );
            } else {
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
            }

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
            self::ensureFrameworkStorageDirectories();

            self::writeStatus(
                'migrating',
                'Atualizando banco de dados',
                82,
                'Executando migrations pendentes — pode demorar vários minutos. Não feche.',
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

            $this->clearLocalPackage(keepMeta: true);
            self::writePackageMeta([
                'download_state' => 'idle',
                'package_ready' => false,
                'check_message' => 'Atualização aplicada. Sistema em '.config('unitec.versao'),
                'local_version' => (string) config('unitec.versao'),
                'last_check_at' => now()->toIso8601String(),
            ]);

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

    private function resolveRemoteVersion(string $downloadUrl): ?string
    {
        $host = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
        if (str_contains($host, 'github.com')) {
            try {
                $response = Http::timeout(20)
                    ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Unitec-ERP-Update'])
                    ->get('https://api.github.com/repos/unitecnologia/unitec-erp-web/releases/tags/update');

                if ($response->successful()) {
                    $name = (string) ($response->json('name') ?? $response->json('tag_name') ?? '');
                    if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $name, $m)) {
                        return $m[1];
                    }
                }
            } catch (\Throwable) {
                // ignore — download ainda pode seguir
            }
        }

        return null;
    }

    private function resolveUpdateDownloadUrl(): string
    {
        try {
            // Mesma ordem da tela Empresa → parâmetros (DB → .env → GitHub).
            $url = trim(ErpSystemConfig::updateDownloadUrl());
        } catch (\Throwable) {
            $url = trim((string) config('unitec.update_download_url', ''));
        }

        $github = 'https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip';

        if ($url === '' || $this->isDeadUpdateHost($url)) {
            return $github;
        }

        return $url;
    }

    private function isDeadUpdateHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, [
            'unitecnologiasistemas.com.br',
            'www.unitecnologiasistemas.com.br',
        ], true);
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
        $enabledPattern = '/^\s*extension\s*=\s*'.preg_quote($extension, '/').'\b/mi';
        $anyPattern = '/^\s*;?\s*extension\s*=\s*'.preg_quote($extension, '/').'\b.*$/mi';
        $replacement = 'extension='.$extension;

        // Já ativo: não mexe (evita "Module zip is already loaded").
        if (preg_match($enabledPattern, $content)) {
            return;
        }

        if (preg_match($anyPattern, $content)) {
            $content = (string) preg_replace($anyPattern, $replacement, $content, 1);
            $content = (string) preg_replace($anyPattern, '', $content);
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
            $this->log($appPath, 'Iniciando php artisan migrate --force');
            Artisan::call('migrate', ['--force' => true]);
            $output = trim((string) Artisan::output());
            $this->log(
                $appPath,
                $output !== ''
                    ? 'Migrate finalizado: '.$output
                    : 'Migrate finalizado (sem pendencias ou sem saida).'
            );
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
