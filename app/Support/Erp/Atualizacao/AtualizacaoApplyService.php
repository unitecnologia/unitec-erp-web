<?php

namespace App\Support\Erp\Atualizacao;

use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\Printing\DeviceServiceLauncher;
use Illuminate\Support\Facades\Artisan;
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
    /**
     * @return array{state: string, percent: int, done: int, total: int, message: string, error?: string}
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
                ...(isset($data['error']) ? ['error' => (string) $data['error']] : []),
            ];
        } catch (\Throwable) {
            return self::idleProgress();
        }
    }

    public static function initializeProgress(?string $appPath = null): void
    {
        self::writeProgress($appPath, [
            'state' => 'starting',
            'percent' => 1,
            'done' => 0,
            'total' => 0,
            'message' => 'Preparando atualização…',
        ]);
    }

    public function apply(?string $appPath = null): string
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
            Log::info('Aplicando atualizacao/ versão '.$version);

            $deviceDistInPackage = DeviceServiceLauncher::packageContainsDist($source);
            if ($deviceDistInPackage) {
                Log::info('Update inclui Device Service dist — parando Unitec.DeviceService.');
                DeviceServiceLauncher::stopRunning();
            }

            $this->copyTree($source, $appPath);

            $this->forgetStalePackageDiscovery($appPath);

            self::writeProgress($appPath, [
                'state' => 'discovering',
                'percent' => 86,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualizando pacotes do Laravel…',
            ]);

            try {
                Artisan::call('package:discover');
            } catch (\Throwable $e) {
                $php = $this->resolvePhp($appPath);
                $this->runShell('"'.$php.'" artisan package:discover --ansi', $appPath);
            }

            if ($deviceDistInPackage) {
                try {
                    DeviceServiceLauncher::ensureRunning();
                } catch (\Throwable $e) {
                    Log::warning('Nao foi possivel religar Device Service apos update: '.$e->getMessage());
                }
            }

            self::writeProgress($appPath, [
                'state' => 'migrating',
                'percent' => 88,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualizando banco de dados…',
            ]);

            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Throwable $e) {
                // Fallback CLI path when called outside HTTP kernel fully booted.
                $php = $this->resolvePhp($appPath);
                $cmd = '"'.$php.'" artisan migrate --force';
                $this->runShell($cmd, $appPath);
            }

            /** @var list<array{command: string, percent: int, message: string}> $cacheSteps */
            $cacheSteps = [
                ['command' => 'package:discover', 'percent' => 94, 'message' => 'Finalizando caches…'],
                ['command' => 'optimize:clear', 'percent' => 94, 'message' => 'Finalizando caches…'],
                ['command' => 'config:cache', 'percent' => 95, 'message' => 'Finalizando caches…'],
                ['command' => 'route:cache', 'percent' => 96, 'message' => 'Finalizando caches…'],
                ['command' => 'view:cache', 'percent' => 97, 'message' => 'Finalizando caches…'],
            ];

            foreach ($cacheSteps as $step) {
                self::writeProgress($appPath, [
                    'state' => 'caching',
                    'percent' => $step['percent'],
                    'done' => 0,
                    'total' => 0,
                    'message' => $step['message'],
                ]);

                try {
                    $this->runArtisanCommand($appPath, $step['command']);
                } catch (\Throwable $e) {
                    Log::warning('Cache pos-atualizacao ('.$step['command'].'): '.$e->getMessage());
                }
            }

            self::writeProgress($appPath, [
                'state' => 'finalizing',
                'percent' => 98,
                'done' => 0,
                'total' => 0,
                'message' => 'Limpando arquivos temporários…',
            ]);

            AtualizacaoPasta::clear($appPath);

            self::writeProgress($appPath, [
                'state' => 'completed',
                'percent' => 100,
                'done' => 0,
                'total' => 0,
                'message' => 'Atualização concluída.',
            ]);

            if (! ErpUpdateProcessLauncher::launchFilamentCaches($appPath)) {
                Log::warning('Nao foi possivel iniciar caches Filament em background pos-atualizacao.');
            }

            return $version;
        } catch (\Throwable $e) {
            self::writeProgress($appPath, [
                'state' => 'failed',
                'percent' => (int) (self::readProgress($appPath)['percent'] ?? 0),
                'done' => 0,
                'total' => 0,
                'message' => 'Falha ao aplicar atualização.',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function copyTree(string $sourceRoot, string $targetRoot): void
    {
        $excludeDirs = [
            'bin',
            'storage',
            'tools',
            'installer',
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
     * @param  array{state: string, percent: int, done: int, total: int, message: string, error?: string}  $progress
     */
    private static function writeProgress(?string $appPath, array $progress): void
    {
        try {
            $path = self::progressPath($appPath);
            File::ensureDirectoryExists(dirname($path));
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

    private function forgetStalePackageDiscovery(string $appPath): void
    {
        $cache = rtrim($appPath, '\\/').DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        foreach (['packages.php', 'services.php'] as $file) {
            $path = $cache.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function resolvePhp(string $appPath): string
    {
        return ErpUpdateProcessLauncher::resolvePhpBinary($appPath);
    }

    private function runArtisanCommand(string $appPath, string $command): void
    {
        try {
            Artisan::call($command);
        } catch (\Throwable $e) {
            $php = $this->resolvePhp($appPath);
            $this->runShell('"'.$php.'" artisan '.$command, $appPath);
        }
    }

    private function runShell(string $command, string $cwd): void
    {
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($command, $descriptor, $pipes, $cwd);
        if (! is_resource($proc)) {
            throw new RuntimeException('Nao foi possivel executar: '.$command);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0) {
            throw new RuntimeException(trim($stderr ?: $stdout) ?: ('Exit '.$code.' em '.$command));
        }
    }
}
