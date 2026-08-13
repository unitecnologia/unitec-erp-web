<?php

namespace App\Support\Erp\Atualizacao;

use App\Support\Erp\ErpUpdateService;
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
    public function apply(?string $appPath = null): string
    {
        $appPath = rtrim($appPath ?: base_path(), '\\/');
        $source = AtualizacaoPasta::filesRoot($appPath);

        if (! AtualizacaoPasta::isPendingNewer($appPath) && ! AtualizacaoPasta::hasArtisanTree($appPath)) {
            throw new RuntimeException('Nenhuma atualização pronta em atualizacao/.');
        }

        if (! AtualizacaoPasta::hasArtisanTree($appPath)) {
            throw new RuntimeException('Pasta atualizacao/ incompleta (falta artisan/vendor).');
        }

        $version = AtualizacaoPasta::pendingVersion($appPath) ?: 'desconhecida';
        Log::info('Aplicando atualizacao/ versão '.$version);

        $this->copyTree($source, $appPath);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            // Fallback CLI path when called outside HTTP kernel fully booted.
            $php = $this->resolvePhp($appPath);
            $cmd = '"'.$php.'" artisan migrate --force';
            $this->runShell($cmd, $appPath);
        }

        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Throwable $e) {
            Log::warning('Cache pos-atualizacao: '.$e->getMessage());
            $php = $this->resolvePhp($appPath);
            foreach (['optimize:clear', 'config:cache', 'route:cache', 'view:cache'] as $art) {
                try {
                    $this->runShell('"'.$php.'" artisan '.$art, $appPath);
                } catch (\Throwable) {
                    // ignore individual
                }
            }
        }

        AtualizacaoPasta::clear($appPath);

        return $version;
    }

    private function copyTree(string $sourceRoot, string $targetRoot): void
    {
        $excludeDirs = [
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
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
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

            $dest = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                File::ensureDirectoryExists($dest);
                continue;
            }

            File::ensureDirectoryExists(dirname($dest));
            if (! @copy($full, $dest)) {
                throw new RuntimeException('Falha ao copiar: '.$relative);
            }
        }

        // Garante config/unitec.php do pacote.
        $srcConfig = $sourceRoot.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'unitec.php';
        $dstConfig = $targetRoot.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'unitec.php';
        if (is_file($srcConfig)) {
            File::copy($srcConfig, $dstConfig);
        }
    }

    private function resolvePhp(string $appPath): string
    {
        $embedded = $appPath.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.'php.exe';
        if (is_file($embedded)) {
            return $embedded;
        }

        return PHP_BINARY ?: 'php';
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
