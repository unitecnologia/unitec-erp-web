<?php

namespace App\Support\Erp\Atualizacao;

use App\Support\Erp\Database\PendingMigrationsGuard;
use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\ErpUpdateService;
use App\Support\Erp\Printing\DeviceServiceLauncher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Copia arquivos de atualizacao/ para a pasta viva (sem ZIP).
 */
final class AtualizacaoApplyService
{
    private string $etapa = 'inicio';

    /** Providers/namespaces DEV que nunca podem ficar em packages.php de produção. */
    private const FORBIDDEN_DEV_PROVIDER_NEEDLES = [
        'Laravel\\Pail\\',
        'Laravel\\Pao\\',
        'Laravel\\Sail\\',
        'NunoMaduro\\Collision\\',
    ];

    /** Nomes Composer DEV que não podem aparecer em packages.php. */
    private const FORBIDDEN_DEV_PACKAGE_NAMES = [
        'laravel/pail',
        'laravel/pao',
        'laravel/sail',
        'nunomaduro/collision',
    ];

    /**
     * @return array{state: string, percent: int, done: int, total: int, message: string, run_id?: string, target_version?: string, error?: string}
     */
    public static function readProgress(?string $appPath = null): array
    {
        $path = self::progressPath($appPath);
        if (! is_file($path)) {
            return self::idleProgress();
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                return self::idleProgress();
            }

            return [
                'state' => (string) ($data['state'] ?? 'idle'),
                'percent' => max(0, min(100, (int) ($data['percent'] ?? 0))),
                'done' => max(0, (int) ($data['done'] ?? 0)),
                'total' => max(0, (int) ($data['total'] ?? 0)),
                'message' => (string) ($data['message'] ?? ''),
                ...(isset($data['run_id']) ? ['run_id' => (string) $data['run_id']] : []),
                ...(isset($data['target_version']) ? ['target_version' => (string) $data['target_version']] : []),
                ...(isset($data['error']) ? ['error' => (string) $data['error']] : []),
            ];
        } catch (\Throwable) {
            return self::idleProgress();
        }
    }

    /**
     * Inicia progresso com run_id exclusivo desta tentativa (evita completed stale na UI).
     */
    public static function initializeProgress(?string $appPath = null, ?string $targetVersion = null): string
    {
        $runId = bin2hex(random_bytes(8));
        self::writeProgress($appPath, [
            'state' => 'starting',
            'percent' => 1,
            'done' => 0,
            'total' => 0,
            'message' => 'Preparando atualização…',
            'run_id' => $runId,
            'target_version' => trim((string) $targetVersion),
        ], preserveIdentity: false);

        return $runId;
    }

    public static function writeFailedProgress(
        ?string $appPath,
        string $error,
        ?string $runId = null,
        ?string $targetVersion = null,
    ): void {
        $payload = [
            'state' => 'failed',
            'percent' => (int) (self::readProgress($appPath)['percent'] ?? 0),
            'done' => 0,
            'total' => 0,
            'message' => 'Falha ao aplicar atualização.',
            'error' => $error,
        ];
        if ($runId !== null && trim($runId) !== '') {
            $payload['run_id'] = trim($runId);
        }
        if ($targetVersion !== null && trim($targetVersion) !== '') {
            $payload['target_version'] = trim($targetVersion);
        }
        self::writeProgress($appPath, $payload, preserveIdentity: $runId === null);
    }

    public function apply(?string $appPath = null, bool $afterCopy = false): string
    {
        $appPath = rtrim($appPath ?: base_path(), '\\/');
        $source = AtualizacaoPasta::filesRoot($appPath);

        try {
            if (! AtualizacaoPasta::isPendingNewer($appPath) && ! AtualizacaoPasta::hasArtisanTree($appPath)) {
                throw new RuntimeException('Nenhuma atualização pronta em atualizacao/.');
            }

            if (! AtualizacaoPasta::hasArtisanTree($appPath)) {
                throw new RuntimeException('Pasta atualizacao/ incompleta (falta artisan/vendor).');
            }

            $version = AtualizacaoPasta::pendingVersion($appPath) ?: 'desconhecida';
            Log::info('Aplicando atualizacao/ versão '.$version.($afterCopy ? ' (apos copia)' : ''));
            $this->etapa = 'Apply';
            AtualizacaoLog::line(
                'Apply',
                'inicio pacote='.$version.' instalada='.(string) config('unitec.versao', '')
                .($afterCopy ? ' modo=after-copy' : ''),
                $appPath,
            );

            $deviceDistInPackage = DeviceServiceLauncher::packageContainsDist($source);

            if (! $afterCopy) {
                if ($deviceDistInPackage) {
                    Log::info('Update inclui Device Service dist — parando Unitec.DeviceService.');
                    DeviceServiceLauncher::stopRunning();
                }

                $this->etapa = 'Copia';
                AtualizacaoLog::line('Copia', 'inicio', $appPath);
                $this->copyTree($source, $appPath);
                AtualizacaoLog::line('Copia', 'ok', $appPath);

                $this->etapa = 'Runtime';
                AtualizacaoLog::line('Runtime', 'inicio', $appPath);
                $runtimePending = $this->stageDesktopRuntimeUpdate($source, $appPath, $version);
                AtualizacaoLog::line('Runtime', $runtimePending ? 'staged' : 'ausente (ok)', $appPath);

                // Codigo novo ja esta em disco: continua num processo PHP fresco
                // (o processo atual ainda tem as classes antigas em memoria).
                return $this->continueAfterCopyInFreshProcess($appPath, $version);
            }

            $serverExe = $this->desktopPendingRoot($appPath)
                .DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'UnitecErpServer.exe';
            $runtimePending = is_file($serverExe);
            if (! $runtimePending) {
                $this->etapa = 'Runtime';
                AtualizacaoLog::line('Runtime', 're-stage apos copia', $appPath);
                $runtimePending = $this->stageDesktopRuntimeUpdate($source, $appPath, $version);
                AtualizacaoLog::line('Runtime', $runtimePending ? 'staged' : 'ausente (ok)', $appPath);
            } else {
                AtualizacaoLog::line('Runtime', 'pending ja staged', $appPath);
            }

            $this->etapa = 'Pacotes';
            AtualizacaoLog::line('Pacotes', 'inicio', $appPath);
            self::writeProgress($appPath, [
                'state' => 'discovering',
                'percent' => 86,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualizando pacotes do Laravel…',
            ]);
            $this->rediscoverPackagesViaCli($appPath);
            AtualizacaoLog::line('Pacotes', 'ok', $appPath);

            if ($deviceDistInPackage) {
                try {
                    DeviceServiceLauncher::ensureRunning();
                } catch (\Throwable $e) {
                    Log::warning('Nao foi possivel religar Device Service apos update: '.$e->getMessage());
                }
            }

            $this->etapa = 'Migrate';
            AtualizacaoLog::line('Migrate', 'inicio', $appPath);
            self::writeProgress($appPath, [
                'state' => 'migrating',
                'percent' => 88,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualizando banco de dados…',
            ]);
            // Migrate via CLI (código/vendor mistos no processo do apply).
            // Com --force, App\Console\Commands\MigrateCommand (código novo após Copia)
            // aplica modo tolerante a tabela/coluna já existente.
            app(PendingMigrationsGuard::class)->invalidate();
            $this->runCliArtisan($appPath, 'migrate --force');
            $this->assertSchemaFullyMigrated($appPath);
            AtualizacaoLog::line('Migrate', 'ok pending=0', $appPath);

            /** @var list<array{command: string, percent: int, message: string, critical?: bool}> $cacheSteps */
            $cacheSteps = [
                ['command' => 'optimize:clear', 'percent' => 94, 'message' => 'Finalizando caches…'],
                ['command' => 'package:discover', 'percent' => 94, 'message' => 'Finalizando caches…', 'critical' => true],
                ['command' => 'config:cache', 'percent' => 95, 'message' => 'Finalizando caches…'],
                ['command' => 'route:cache', 'percent' => 96, 'message' => 'Finalizando caches…'],
                ['command' => 'view:cache', 'percent' => 97, 'message' => 'Finalizando caches…'],
            ];

            $this->etapa = 'Caches';
            AtualizacaoLog::line('Caches', 'inicio', $appPath);
            foreach ($cacheSteps as $step) {
                self::writeProgress($appPath, [
                    'state' => 'caching',
                    'percent' => $step['percent'],
                    'done' => 0,
                    'total' => 0,
                    'message' => $step['message'],
                ]);

                $critical = (bool) ($step['critical'] ?? false)
                    || str_starts_with(trim($step['command']), 'package:discover');

                try {
                    if (str_starts_with(trim($step['command']), 'package:discover')) {
                        $this->rediscoverPackagesViaCli($appPath);
                    } else {
                        // Sempre CLI novo — nunca Artisan::call no processo do apply.
                        $this->runCliArtisan($appPath, $step['command']);
                    }
                } catch (\Throwable $e) {
                    AtualizacaoLog::line('Caches', $step['command'].' falhou: '.$e->getMessage(), $appPath);
                    if ($critical) {
                        throw $e;
                    }
                    // config/route/view cache ausente nao impede boot; ainda assim logamos.
                    Log::warning('Cache pos-atualizacao ('.$step['command'].'): '.$e->getMessage());
                }
            }
            AtualizacaoLog::line('Caches', 'ok', $appPath);

            $installedAfterCopy = ErpUpdateService::readInstalledVersion($appPath);
            if ($version !== 'desconhecida' && strcasecmp($installedAfterCopy, $version) !== 0) {
                throw new RuntimeException(
                    'Versão em disco ('.$installedAfterCopy.') difere do pacote ('.$version.') após a cópia.'
                );
            }
            AtualizacaoLog::line('Versao', 'disco='.$installedAfterCopy.' pacote='.$version, $appPath);

            // Runtime obrigatório: só conclui após status persistente completed/skipped_newer do PS1.
            if ($runtimePending) {
                $this->etapa = 'RuntimeApply';
                self::writeProgress($appPath, [
                    'state' => 'runtime',
                    'percent' => 97,
                    'done' => 0,
                    'total' => 0,
                    'message' => 'Aplicando runtime desktop (reinício do serviço)…',
                ]);
                $this->applyAndWaitDesktopRuntime($appPath, $version);
            }

            $this->etapa = 'Health';
            self::writeProgress($appPath, [
                'state' => 'finalizing',
                'percent' => 98,
                'done' => 0,
                'total' => 0,
                'message' => 'Verificando saúde do ERP (versão '.$version.')…',
            ]);
            // Runtime já aplicado (ou ausente): não reagendar PS1 no recover.
            $this->ensureHealthyOrRecover($appPath, $version, false);

            $installedFinal = ErpUpdateService::readInstalledVersion($appPath);
            if ($version !== 'desconhecida' && strcasecmp($installedFinal, $version) !== 0) {
                throw new RuntimeException(
                    'Versão final em disco ('.$installedFinal.') difere do pacote ('.$version.').'
                );
            }

            $this->etapa = 'Limpeza';
            AtualizacaoLog::line('Limpeza', 'inicio', $appPath);
            AtualizacaoPasta::clear($appPath);
            AtualizacaoLog::line('Limpeza', 'ok', $appPath);

            self::writeProgress($appPath, [
                'state' => 'completed',
                'percent' => 100,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualização concluída.',
                'target_version' => $version,
            ]);

            if (! ErpUpdateProcessLauncher::launchFilamentCaches($appPath)) {
                Log::warning('Nao foi possivel iniciar caches Filament em background pos-atualizacao.');
                AtualizacaoLog::line('Caches', 'nao foi possivel iniciar caches Filament em background', $appPath);
            }

            AtualizacaoLog::line('Concluida', 'versao='.$version, $appPath);

            return $version;
        } catch (\Throwable $e) {
            $hint = $e->getMessage();
            if (! str_contains(mb_strtolower($hint), 'reparar sistema')) {
                $hint .= ' Rode Reparar Sistema.bat se o ERP continuar em HTTP 500.';
            }

            self::writeProgress($appPath, [
                'state' => 'failed',
                'percent' => (int) (self::readProgress($appPath)['percent'] ?? 0),
                'done' => 0,
                'total' => 0,
                'message' => 'Falha ao aplicar atualização.',
                'error' => $hint,
            ]);

            AtualizacaoLog::line('Falha', 'etapa='.$this->etapa.' '.$hint, $appPath);

            throw new RuntimeException($hint, (int) $e->getCode(), $e);
        }
    }

    private function copyTree(string $sourceRoot, string $targetRoot): void
    {
        $excludeDirs = [
            'bin',
            'storage',
            'tools',
            'installer',
            // Aplicado em applyDesktopRuntimeUpdate (FrankenPHP + UnitecErpServer).
            'runtime-update',
            'node_modules',
            '.git',
            'dist',
            '.cursor',
            '.idea',
            '.vscode',
            'atualizacao',
            'staging',
            'tests',
            'docs',
            // Migracoes one-off (SCV etc.): nunca aplicar no cliente.
            'migracoes',
        ];

        $excludeFiles = [
            '.env',
            '.env.backup',
            '.env.production',
            'ready.json',
            'manifest.json',
            'composer.phar',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var list<array{item: SplFileInfo, relative: string}> $files */
        $files = [];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $full = $item->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($full, strlen($sourceRoot))), '/');
            if ($relative === '' || $relative === 'ready.json' || $relative === 'manifest.json') {
                continue;
            }

            $parts = explode('/', $relative);
            $top = $parts[0] ?? '';
            if (in_array($top, $excludeDirs, true)) {
                continue;
            }

            $base = basename($relative);
            if (in_array($base, $excludeFiles, true)) {
                continue;
            }

            // Nunca copiar cache de bootstrap do pacote (packages.php com Pail etc.).
            if (str_starts_with($relative, 'bootstrap/cache/') && str_ends_with($relative, '.php')) {
                continue;
            }

            // DLL do OpenSSL (legacy provider) fica carregada com o PHP no ar —
            // copy() falha no Windows. Mantém a do cliente; .cnf continua atualizando.
            if ($this->isOpenSslProviderBinary($relative)) {
                continue;
            }

            $files[] = ['item' => $item, 'relative' => $relative];
        }

        $total = count($files);
        $step = max(1, (int) ceil(max(1, $total) / 100));

        self::writeProgress($targetRoot, [
            'state' => 'copying',
            'percent' => 5,
            'done' => 0,
            'total' => $total,
            'message' => 'Copiando arquivos…',
        ]);

        foreach ($files as $index => $entry) {
            $item = $entry['item'];
            $relative = $entry['relative'];
            $full = $item->getPathname();
            $dest = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            File::ensureDirectoryExists(dirname($dest));
            if (! @copy($full, $dest)) {
                throw new RuntimeException('Falha ao copiar: '.$relative);
            }

            $done = $index + 1;
            if ($done === $total || $done % $step === 0) {
                $percent = 5 + (int) floor(($done / max(1, $total)) * 80);
                self::writeProgress($targetRoot, [
                    'state' => 'copying',
                    'percent' => min(85, $percent),
                    'done' => $done,
                    'total' => $total,
                    'message' => 'Copiando arquivos…',
                ]);
            }
        }
    }

    private static function progressPath(?string $appPath = null): string
    {
        $root = rtrim($appPath ?: base_path(), '\\/');

        return $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'atualizacao-apply-progress.json';
    }

    /**
     * @param  array{state: string, percent: int, done: int, total: int, message: string, run_id?: string, target_version?: string, error?: string}  $progress
     */
    private static function writeProgress(?string $appPath, array $progress, bool $preserveIdentity = true): void
    {
        try {
            $path = self::progressPath($appPath);
            File::ensureDirectoryExists(dirname($path));

            if ($preserveIdentity && is_file($path)) {
                try {
                    $current = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($current)) {
                        if (! isset($progress['run_id']) && isset($current['run_id'])) {
                            $progress['run_id'] = (string) $current['run_id'];
                        }
                        if (! array_key_exists('target_version', $progress) && isset($current['target_version'])) {
                            $progress['target_version'] = (string) $current['target_version'];
                        }
                    }
                } catch (\Throwable) {
                }
            }

            $progress['updated_at'] = now()->toIso8601String();
            file_put_contents(
                $path,
                json_encode($progress, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao gravar progresso da atualização.', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @return array{state: string, percent: int, done: int, total: int, message: string}
     */
    private static function idleProgress(): array
    {
        return [
            'state' => 'idle',
            'percent' => 0,
            'done' => 0,
            'total' => 0,
            'message' => 'Aguardando atualização.',
        ];
    }

    private function isOpenSslProviderBinary(string $relative): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $relative));
        if (! str_starts_with($normalized, 'resources/ssl/openssl/')) {
            return false;
        }

        return (bool) preg_match('/\.(dll|so|dylib)$/', $normalized);
    }

    /**
     * Apaga packages.php e services.php com retry. Falha se o arquivo continuar existindo.
     */
    private function deletePackageDiscoveryCaches(string $appPath): void
    {
        $cache = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        if (! is_dir($cache)) {
            if (! @mkdir($cache, 0775, true) && ! is_dir($cache)) {
                throw new RuntimeException('Nao foi possivel criar bootstrap/cache.');
            }
        }

        foreach (['packages.php', 'services.php'] as $file) {
            $path = $cache.DIRECTORY_SEPARATOR.$file;
            if (! is_file($path)) {
                continue;
            }

            $removed = false;
            for ($i = 0; $i < 8; $i++) {
                if (@unlink($path)) {
                    clearstatcache(true, $path);
                    if (! is_file($path)) {
                        $removed = true;
                        break;
                    }
                }
                usleep(250_000);
                clearstatcache(true, $path);
                if (! is_file($path)) {
                    $removed = true;
                    break;
                }
            }

            clearstatcache(true, $path);
            if (! $removed || is_file($path)) {
                throw new RuntimeException(
                    'Nao foi possivel remover bootstrap/cache/'.$file
                    .' (arquivo em uso). Feche o ERP/FrankenPHP e rode Reparar Sistema.bat.'
                );
            }
        }
    }

    /**
     * package:discover somente via PHP CLI (processo novo), com validação anti-DEV.
     */
    private function rediscoverPackagesViaCli(string $appPath): void
    {
        $this->deletePackageDiscoveryCaches($appPath);
        $this->runCliArtisan($appPath, 'package:discover --ansi');
        $hits = $this->findForbiddenDevMarkersInManifest($appPath);
        if ($hits === []) {
            return;
        }

        AtualizacaoLog::line(
            'Pacotes',
            'manifesto com providers DEV: '.implode(', ', $hits).' — limpando e rediscover',
            $appPath,
        );
        Log::warning('packages.php continha providers DEV apos discover', ['hits' => $hits]);

        $this->deletePackageDiscoveryCaches($appPath);
        $this->runCliArtisan($appPath, 'package:discover --ansi');
        $hitsAgain = $this->findForbiddenDevMarkersInManifest($appPath);
        if ($hitsAgain !== []) {
            throw new RuntimeException(
                'packages.php continua com providers DEV ('.implode(', ', $hitsAgain)
                .') apos rediscover. Rode Reparar Sistema.bat.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function findForbiddenDevMarkersInManifest(string $appPath): array
    {
        $path = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'bootstrap'
            .DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'packages.php';
        if (! is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
        $hits = [];

        foreach (self::FORBIDDEN_DEV_PROVIDER_NEEDLES as $needle) {
            if (str_contains($raw, $needle)) {
                $hits[] = $needle;
            }
        }

        /** @var mixed $manifest */
        $manifest = @include $path;
        if (! is_array($manifest)) {
            return array_values(array_unique($hits));
        }

        $packages = is_array($manifest['packages'] ?? null) ? $manifest['packages'] : [];
        foreach (array_keys($packages) as $name) {
            $lower = strtolower((string) $name);
            if (in_array($lower, self::FORBIDDEN_DEV_PACKAGE_NAMES, true)) {
                $hits[] = $lower;
            }
        }

        foreach (['providers', 'eager', 'deferred'] as $key) {
            if (! isset($manifest[$key]) || ! is_array($manifest[$key])) {
                continue;
            }
            foreach ($manifest[$key] as $k => $v) {
                $class = is_string($k) && ! is_int($k) ? (string) $k : (is_string($v) ? $v : '');
                if ($class === '') {
                    continue;
                }
                $class = ltrim($class, '\\');
                foreach (self::FORBIDDEN_DEV_PROVIDER_NEEDLES as $needle) {
                    if (str_starts_with($class, $needle) || str_contains($class, rtrim($needle, '\\'))) {
                        $hits[] = $class;
                    }
                }
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * Aguarda /api/health com HTTP 2xx + status=ok + version=alvo.
     * Em HTTP 500 tenta recuperação (máx. 2 ciclos). Versão antiga ≠ sucesso.
     */
    private function ensureHealthyOrRecover(string $appPath, string $expectedVersion, bool $runtimePending): void
    {
        $expectedVersion = trim($expectedVersion);
        $maxAttempts = $runtimePending ? 90 : 40;
        $delayMs = 500;
        $maxRecoveries = 2;
        $recoveries = 0;
        $result = [
            'ok' => false,
            'status' => null,
            'body' => '',
            'message' => 'Sem tentativas.',
            'health_status' => null,
            'health_version' => null,
        ];

        for ($cycle = 0; $cycle <= $maxRecoveries; $cycle++) {
            $result = $this->waitForHealth($expectedVersion, $maxAttempts, $delayMs);
            if ($result['ok']) {
                AtualizacaoLog::line(
                    'Health',
                    'OK http='.(string) ($result['status'] ?? 200)
                    .' status='.(string) ($result['health_status'] ?? '')
                    .' version='.(string) ($result['health_version'] ?? '')
                    .' (alvo='.$expectedVersion.')',
                    $appPath,
                );

                return;
            }

            $status = $result['status'];
            $isHttp500 = $status === 500;
            $canRecover = $isHttp500 && $recoveries < $maxRecoveries;

            AtualizacaoLog::line(
                'Health',
                'falhou status='.($status === null ? 'sem-resposta' : (string) $status)
                .' health_version='.(string) ($result['health_version'] ?? '')
                .' alvo='.$expectedVersion
                .' msg='.mb_substr($result['message'] ?? '', 0, 200),
                $appPath,
            );

            if (! $canRecover) {
                break;
            }

            $recoveries++;
            self::writeProgress($appPath, [
                'state' => 'finalizing',
                'percent' => 98,
                'done' => 0,
                'total' => 0,
                'message' => 'Recuperando caches do Laravel…',
            ]);
            AtualizacaoLog::line(
                'Health',
                'recuperacao automatica '.$recoveries.'/'.$maxRecoveries
                .' (del packages/services + package:discover + caches CLI)',
                $appPath,
            );

            $this->rediscoverPackagesViaCli($appPath);
            try {
                $this->runCliArtisan($appPath, 'optimize:clear');
            } catch (\Throwable $e) {
                AtualizacaoLog::line('Health', 'optimize:clear na recuperacao: '.$e->getMessage(), $appPath);
            }
            $this->rediscoverPackagesViaCli($appPath);
            foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
                try {
                    $this->runCliArtisan($appPath, $cmd);
                } catch (\Throwable $e) {
                    AtualizacaoLog::line('Health', $cmd.' na recuperacao: '.$e->getMessage(), $appPath);
                }
            }

            if ($runtimePending) {
                AtualizacaoLog::line('Health', 'recuperacao com runtime pendente — aguardando PS1', $appPath);
                try {
                    $this->applyAndWaitDesktopRuntime($appPath, $expectedVersion);
                } catch (\Throwable $e) {
                    AtualizacaoLog::line('Health', 'runtime na recuperacao: '.$e->getMessage(), $appPath);
                }
            }

            $maxAttempts = $runtimePending ? 60 : 30;
        }

        $detail = $result['message'] ?? '';
        throw new RuntimeException(
            'ERP nao ficou saudavel apos a atualizacao (alvo='.$expectedVersion.'). '
            .$detail
            .' Rode Reparar Sistema.bat (apaga bootstrap/cache/packages.php e services.php + package:discover).'
        );
    }

    /**
     * @return array{ok: bool, status: int|null, body: string, message: string, health_status: ?string, health_version: ?string}
     */
    private function waitForHealth(string $expectedVersion, int $maxAttempts, int $delayMs): array
    {
        $last = [
            'ok' => false,
            'status' => null,
            'body' => '',
            'message' => 'Sem tentativas.',
            'health_status' => null,
            'health_version' => null,
        ];

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $last = $this->probeHealth($expectedVersion);
            if ($last['ok']) {
                return $last;
            }

            // 500: sai cedo para recovery. Versao antiga continua esperando o novo runtime.
            if ($last['status'] === 500 && $i >= 3) {
                return $last;
            }

            usleep(max(100_000, $delayMs * 1000));
        }

        return $last;
    }

    /**
     * GET /api/health — sucesso só com HTTP 2xx, JSON status=ok e version=alvo.
     *
     * @return array{ok: bool, status: int|null, body: string, message: string, health_status: ?string, health_version: ?string}
     */
    private function probeHealth(string $expectedVersion = ''): array
    {
        $url = 'http://127.0.0.1:8765/api/health';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2.5,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nConnection: close\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        $status = null;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) {
                    $status = (int) $m[1];
                    break;
                }
            }
        }

        if ($body === false && $status === null) {
            return [
                'ok' => false,
                'status' => null,
                'body' => '',
                'message' => 'Sem resposta de '.$url,
                'health_status' => null,
                'health_version' => null,
            ];
        }

        $bodyStr = is_string($body) ? $body : '';
        $parsed = $this->parseHealthPayload($bodyStr);
        $expectedVersion = trim($expectedVersion);

        $okHttp = $status !== null && $status >= 200 && $status < 300;
        $okStatus = strcasecmp((string) ($parsed['status'] ?? ''), 'ok') === 0;
        $reportedVersion = trim((string) ($parsed['version'] ?? ''));
        $okVersion = $expectedVersion === ''
            || ($reportedVersion !== '' && strcasecmp($reportedVersion, $expectedVersion) === 0);

        $ok = $okHttp && $okStatus && $okVersion;

        $message = 'OK';
        if (! $okHttp) {
            $message = 'HTTP '.($status ?? 0).' em /api/health';
        } elseif (! $okStatus) {
            $message = 'health status='.(string) ($parsed['status'] ?? 'ausente').' (esperado ok)';
        } elseif (! $okVersion) {
            $message = 'health version='.($reportedVersion !== '' ? $reportedVersion : 'ausente')
                .' (esperado '.$expectedVersion.' — runtime antigo ainda respondendo?)';
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $bodyStr,
            'message' => $message,
            'health_status' => isset($parsed['status']) ? (string) $parsed['status'] : null,
            'health_version' => $reportedVersion !== '' ? $reportedVersion : null,
        ];
    }

    /**
     * @return array{status?: string, version?: string}
     */
    private function parseHealthPayload(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $out = [];
        if (isset($data['status'])) {
            $out['status'] = trim((string) $data['status']);
        }
        if (isset($data['version'])) {
            $out['version'] = trim((string) $data['version']);
        }

        return $out;
    }

    /**
     * Prepara FrankenPHP + binários do UnitecErpServer em pasta pendente.
     * Não sobrescreve tools/frankenphp nem bin/ ao vivo (DLLs em uso).
     * Nunca substitui pending/manifesto mais novo por pacote mais antigo.
     */
    private function stageDesktopRuntimeUpdate(string $sourceRoot, string $appPath, string $packageVersion): bool
    {
        $runtime = rtrim($sourceRoot, '\\/').DIRECTORY_SEPARATOR.'runtime-update';
        $frankenSrc = $runtime.DIRECTORY_SEPARATOR.'frankenphp';
        $binSrc = $runtime.DIRECTORY_SEPARATOR.'bin';
        $frankenExe = $frankenSrc.DIRECTORY_SEPARATOR.'frankenphp.exe';
        $serverExe = $binSrc.DIRECTORY_SEPARATOR.'UnitecErpServer.exe';

        if (! is_dir($runtime) || ! is_file($frankenExe) || ! is_file($serverExe)) {
            Log::info('Update sem runtime-update — mantendo runtime desktop atual.');

            return false;
        }

        $installedRuntime = $this->readDesktopRuntimeMarkerVersion($appPath);
        if ($installedRuntime !== '' && $packageVersion !== 'desconhecida'
            && AtualizacaoPasta::compareVersions($installedRuntime, $packageVersion) > 0) {
            AtualizacaoLog::line(
                'Runtime',
                'instalado '.$installedRuntime.' > pacote '.$packageVersion.' — nao empilha runtime antigo',
                $appPath,
            );

            return false;
        }

        $pendingRoot = $this->desktopPendingRoot($appPath);
        $existingPendingVersion = $this->readDesktopPendingManifestVersion($appPath);
        $existingServer = $pendingRoot.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'UnitecErpServer.exe';

        if (is_file($existingServer) && $existingPendingVersion !== '' && $packageVersion !== 'desconhecida'
            && AtualizacaoPasta::compareVersions($existingPendingVersion, $packageVersion) > 0) {
            AtualizacaoLog::line(
                'Runtime',
                'pending '.$existingPendingVersion.' > pacote '.$packageVersion.' — mantem pending mais novo',
                $appPath,
            );

            return true;
        }

        self::writeProgress($appPath, [
            'state' => 'copying',
            'percent' => 84,
            'done' => 0,
            'total' => 0,
            'message' => 'Preparando runtime HTTP (FrankenPHP)…',
        ]);

        $pendingFranken = $pendingRoot.DIRECTORY_SEPARATOR.'frankenphp';
        $pendingBin = $pendingRoot.DIRECTORY_SEPARATOR.'bin';

        File::ensureDirectoryExists($pendingFranken);
        $this->copyDirectoryRecursive($frankenSrc, $pendingFranken);
        AtualizacaoLog::line('Runtime', 'frankenphp pendente em storage/app/unitec-desktop-pending/frankenphp', $appPath);

        File::ensureDirectoryExists($pendingBin);
        $this->copyDirectoryRecursive($binSrc, $pendingBin);
        AtualizacaoLog::line('Runtime', 'bin pendente em storage/app/unitec-desktop-pending/bin', $appPath);

        $this->writeDesktopPendingManifest($appPath, $packageVersion);
        $this->writeDesktopRuntimeStatus($appPath, [
            'state' => 'waiting',
            'message' => 'Runtime staged; aguardando apply.',
            'expected_version' => $packageVersion,
            'health_version' => '',
            'run_id' => trim((string) (self::readProgress($appPath)['run_id'] ?? '')),
        ]);

        return true;
    }

    /**
     * Sinaliza o PS1, aguarda status persistente (fora do pending) com run_id desta tentativa.
     */
    private function applyAndWaitDesktopRuntime(string $appPath, string $expectedVersion): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            AtualizacaoLog::line('Runtime', 'plataforma nao Windows — skip runtime desktop', $appPath);

            return;
        }

        $pendingRoot = $this->desktopPendingRoot($appPath);
        $serverExe = $pendingRoot.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'UnitecErpServer.exe';
        if (! is_file($serverExe)) {
            throw new RuntimeException('Runtime pendente incompleto (falta UnitecErpServer.exe). Pending preservado.');
        }

        $runId = trim((string) (self::readProgress($appPath)['run_id'] ?? ''));
        if ($runId === '') {
            $runId = bin2hex(random_bytes(8));
            AtualizacaoLog::line('Runtime', 'run_id ausente no progress — gerado '.$runId, $appPath);
        }

        $this->writeDesktopRuntimeStatus($appPath, [
            'state' => 'waiting',
            'message' => 'Aguardando script de runtime.',
            'expected_version' => $expectedVersion,
            'health_version' => '',
            'run_id' => $runId,
        ]);

        File::ensureDirectoryExists($pendingRoot);
        File::put($pendingRoot.DIRECTORY_SEPARATOR.'ready.signal', now()->toIso8601String());

        if (! $this->launchDesktopRuntimeUpdateScript($appPath, $expectedVersion, $runId)) {
            $this->writeDesktopRuntimeStatus($appPath, [
                'state' => 'failed',
                'message' => 'Nao foi possivel iniciar apply-desktop-runtime-update.ps1.',
                'expected_version' => $expectedVersion,
                'health_version' => '',
                'run_id' => $runId,
            ]);
            throw new RuntimeException(
                'Nao foi possivel iniciar apply-desktop-runtime-update.ps1. Pending preservado em storage/app/unitec-desktop-pending.'
            );
        }

        AtualizacaoLog::line(
            'Runtime',
            'script iniciado — aguardando desktop-runtime-status.json (versao='.$expectedVersion.' run_id='.$runId.')',
            $appPath,
        );

        $deadline = time() + 360;
        while (time() < $deadline) {
            $status = $this->readDesktopRuntimeStatus($appPath);
            $statusRunId = trim((string) ($status['run_id'] ?? ''));
            // Ignora status de outra tentativa (arquivo residual).
            if ($statusRunId !== '' && $statusRunId !== $runId) {
                usleep(500_000);

                continue;
            }

            $state = (string) ($status['state'] ?? '');
            $message = trim((string) ($status['message'] ?? ''));

            self::writeProgress($appPath, [
                'state' => 'runtime',
                'percent' => 97,
                'done' => 0,
                'total' => 0,
                'message' => $message !== ''
                    ? $message
                    : 'Aplicando runtime desktop (reinício do serviço)…',
            ]);

            if (in_array($state, ['completed', 'skipped_newer'], true)) {
                AtualizacaoLog::line('Runtime', 'status='.$state.' '.$message.' run_id='.$runId, $appPath);
                // Pending pode ja ter sido limpo pelo PS1; reforca limpeza sem tocar no status.
                $this->clearDesktopPendingFolder($appPath);

                return;
            }

            if ($state === 'failed') {
                throw new RuntimeException(
                    ($message !== '' ? $message : 'Falha ao aplicar runtime desktop.')
                    .' Pending preservado em storage/app/unitec-desktop-pending.'
                );
            }

            usleep(500_000);
        }

        $this->writeDesktopRuntimeStatus($appPath, [
            'state' => 'timeout',
            'message' => 'Timeout aguardando runtime desktop (status persistente).',
            'expected_version' => $expectedVersion,
            'health_version' => '',
            'run_id' => $runId,
        ]);

        throw new RuntimeException(
            'Timeout aguardando runtime desktop (status persistente). Pending preservado — tente novamente a mesma atualização.'
        );
    }

    private function copyDirectoryRecursive(string $sourceDir, string $destDir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($sourceDir))), '/');
            if ($relative === '') {
                continue;
            }

            $dest = $destDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                File::ensureDirectoryExists($dest);

                continue;
            }

            File::ensureDirectoryExists(dirname($dest));
            if (! @copy($item->getPathname(), $dest)) {
                throw new RuntimeException('Falha ao copiar runtime: '.$relative);
            }
        }
    }

    private function launchDesktopRuntimeUpdateScript(
        string $appPath,
        string $expectedVersion = '',
        string $runId = '',
    ): bool {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $script = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'scripts'
            .DIRECTORY_SEPARATOR.'apply-desktop-runtime-update.ps1';
        if (! is_file($script)) {
            Log::warning('apply-desktop-runtime-update.ps1 ausente apos copia — reinicie o UnitecErpServer manualmente.');
            AtualizacaoLog::line('Runtime', 'script ausente — reinicio manual necessario', $appPath);

            return false;
        }

        $logFile = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'
            .DIRECTORY_SEPARATOR.'desktop-runtime-update.log';
        File::ensureDirectoryExists(dirname($logFile));

        $batch = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'erp-desktop-runtime-update.bat';
        $quote = static fn (string $value): string => str_replace('"', '""', $value);
        File::ensureDirectoryExists(dirname($batch));

        $psArgs = '-NoProfile -ExecutionPolicy Bypass -File "'.$quote($script).'" -AppPath "'.$quote($appPath).'"';
        if (trim($expectedVersion) !== '' && $expectedVersion !== 'desconhecida') {
            $psArgs .= ' -ExpectedVersion "'.$quote($expectedVersion).'"';
        }
        if (trim($runId) !== '') {
            $psArgs .= ' -RunId "'.$quote($runId).'"';
        }

        File::put($batch, implode("\r\n", [
            '@echo off',
            'chcp 65001 >nul',
            'powershell '.$psArgs.' >> "'.$quote($logFile).'" 2>&1',
        ])."\r\n");

        $handle = @popen('start "" /B cmd /C '.escapeshellarg($batch), 'r');
        if ($handle === false) {
            Log::warning('Nao foi possivel agendar apply-desktop-runtime-update.ps1');
            AtualizacaoLog::line('Runtime', 'falha ao agendar reinicio do servico', $appPath);

            return false;
        }

        pclose($handle);

        return true;
    }

    private function desktopPendingRoot(string $appPath): string
    {
        return rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'unitec-desktop-pending';
    }

    private function desktopRuntimeStatusPath(string $appPath): string
    {
        return rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'desktop-runtime-status.json';
    }

    private function desktopRuntimeMarkerPath(string $appPath): string
    {
        return rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'unitec-desktop-runtime.json';
    }

    private function clearDesktopPendingFolder(string $appPath): void
    {
        $root = $this->desktopPendingRoot($appPath);
        if (! is_dir($root)) {
            return;
        }

        try {
            File::deleteDirectory($root);
        } catch (\Throwable $e) {
            Log::warning('Falha ao limpar unitec-desktop-pending apos runtime OK.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function writeDesktopPendingManifest(string $appPath, string $packageVersion): void
    {
        $root = $this->desktopPendingRoot($appPath);
        File::ensureDirectoryExists($root);
        File::put($root.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'erp_version' => $packageVersion,
            'staged_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function readDesktopPendingManifestVersion(string $appPath): string
    {
        $path = $this->desktopPendingRoot($appPath).DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($path)) {
            return '';
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? trim((string) ($data['erp_version'] ?? '')) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function readDesktopRuntimeMarkerVersion(string $appPath): string
    {
        $path = $this->desktopRuntimeMarkerPath($appPath);
        if (! is_file($path)) {
            return '';
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? trim((string) ($data['erp_version'] ?? '')) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Status persistente fora do pending (evita corrida com Remove-Item do PS1).
     *
     * @param  array{state: string, message?: string, expected_version?: string, health_version?: string, run_id?: string}  $status
     */
    private function writeDesktopRuntimeStatus(string $appPath, array $status): void
    {
        $path = $this->desktopRuntimeStatusPath($appPath);
        File::ensureDirectoryExists(dirname($path));
        $status['updated_at'] = now()->toIso8601String();
        File::put($path, json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{state?: string, message?: string, expected_version?: string, health_version?: string, run_id?: string}
     */
    private function readDesktopRuntimeStatus(string $appPath): array
    {
        foreach ([
            $this->desktopRuntimeStatusPath($appPath),
            $this->desktopPendingRoot($appPath).DIRECTORY_SEPARATOR.'status.json',
        ] as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && $data !== []) {
                    return $data;
                }
            } catch (\Throwable) {
                // tenta o proximo caminho
            }
        }

        return [];
    }

    /**
     * Apos copyTree o disco ja tem o codigo novo; este processo ainda carrega o antigo.
     * Dispara um filho --after-copy e propaga o resultado do progress.
     */
    private function continueAfterCopyInFreshProcess(string $appPath, string $version): string
    {
        AtualizacaoLog::line('Apply', 'handoff --after-copy (PHP fresco apos copia)', $appPath);
        self::writeProgress($appPath, [
            'state' => 'copying',
            'percent' => 85,
            'done' => 0,
            'total' => 0,
            'message' => 'Reiniciando apply com o código novo…',
            'target_version' => $version,
        ]);

        $php = $this->resolvePhp($appPath);
        $command = '"'.$php.'" -d opcache.enable=0 -d opcache.enable_cli=0 artisan'
            .' unitec:apply-atualizacao --app-path="'.$appPath.'" --after-copy';

        try {
            $this->runShell($command, $appPath);
        } catch (\Throwable $e) {
            $progress = self::readProgress($appPath);
            $fromProgress = trim((string) ($progress['error'] ?? ''));
            if (($progress['state'] ?? '') === 'failed' && $fromProgress !== '') {
                throw new RuntimeException($fromProgress, (int) $e->getCode(), $e);
            }

            throw $e;
        }

        $progress = self::readProgress($appPath);
        if (($progress['state'] ?? '') === 'completed') {
            $done = trim((string) ($progress['target_version'] ?? ''));

            return $done !== '' ? $done : $version;
        }

        $error = trim((string) ($progress['error'] ?? ''));
        throw new RuntimeException(
            $error !== '' ? $error : 'Falha ao continuar a atualização após a cópia.'
        );
    }

    private function resolvePhp(string $appPath): string
    {
        return ErpUpdateProcessLauncher::resolvePhpBinary($appPath);
    }

    /**
     * Após migrate --force: exige zero pendências. Em falha mantém atualizacao/
     * (clear só ocorre no sucesso) e impede concluir o apply.
     */
    private function assertSchemaFullyMigrated(string $appPath): void
    {
        self::writeProgress($appPath, [
            'state' => 'migrating',
            'percent' => 90,
            'done' => 0,
            'total' => 0,
            'message' => 'Confirmando schema do banco…',
        ]);

        try {
            app(PendingMigrationsGuard::class)->assertZeroPending();
        } catch (\Throwable $e) {
            $detail = trim($e->getMessage());
            AtualizacaoLog::line('Migrate', 'schema ainda incompatível: '.$detail, $appPath);
            Log::error('Atualização bloqueada: schema incompatível após migrate.', [
                'message' => $detail,
            ]);

            throw new RuntimeException(
                'Atualização não concluída: o banco continua incompatível com o código após o migrate. '
                .($detail !== '' ? $detail.' ' : '')
                .'A pasta atualizacao/ foi mantida. Aceite a atualização novamente no login ou execute Reparar Sistema.bat.',
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function runCliArtisan(string $appPath, string $artisanArgs): void
    {
        $php = $this->resolvePhp($appPath);
        $command = '"'.$php.'" artisan '.$artisanArgs;
        $this->runShell($command, $appPath);
    }

    private function runShell(string $command, string $cwd): void
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($command, $descriptor, $pipes, $cwd);
        if (! is_resource($proc)) {
            throw new RuntimeException('Nao foi possivel executar: '.$command);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        AtualizacaoLog::line(
            'Shell',
            'exit='.$code.' cmd='.mb_substr($command, 0, 160)
            .' stdout='.mb_substr(trim($stdout), 0, 200)
            .' stderr='.mb_substr(trim($stderr), 0, 200),
            $cwd,
        );

        if ($code !== 0) {
            throw new RuntimeException(
                trim($stderr !== '' ? $stderr : $stdout) ?: ('Exit '.$code.' em '.$command)
            );
        }
    }
}
