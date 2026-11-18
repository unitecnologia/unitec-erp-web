<?php

namespace App\Support\Erp;

use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Filesystem com replace() resistente a rename() "Acesso negado (code: 5)" no Windows.
 *
 * O Laravel grava packages.php, services.php e views compiladas via rename(tmp, destino).
 * No Windows o destino fica travado por FrankenPHP/antivírus e o rename falha.
 */
class WindowsSafeFilesystem extends Filesystem
{
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);
        $path = realpath($path) ?: $path;

        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Diretório indisponível para gravação: '.$dir);
        }

        $tempPath = tempnam($dir, basename($path));
        if ($tempPath === false) {
            // Fallback extremo: grava direto (melhor que derrubar o ERP).
            if (file_put_contents($path, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Falha ao gravar: '.$path);
            }

            return;
        }

        if (! is_null($mode)) {
            @chmod($tempPath, $mode);
        } else {
            @chmod($tempPath, 0777 - umask());
        }

        if (file_put_contents($tempPath, $content) === false) {
            @unlink($tempPath);
            throw new \RuntimeException('Falha ao gravar temporário: '.$tempPath);
        }

        $lastError = null;
        for ($i = 0; $i < 8; $i++) {
            try {
                if (@rename($tempPath, $path)) {
                    return;
                }
            } catch (Throwable $e) {
                $lastError = $e;
            }

            // Destino travado: tenta liberar e renomear de novo.
            if (is_file($path)) {
                @chmod($path, 0666);
                @unlink($path);
                clearstatcache(true, $path);
            }

            try {
                if (@rename($tempPath, $path)) {
                    return;
                }
            } catch (Throwable $e) {
                $lastError = $e;
            }

            // Último recurso desta tentativa: copy + apaga tmp.
            if (@copy($tempPath, $path)) {
                @unlink($tempPath);
                clearstatcache(true, $path);

                return;
            }

            usleep(50_000 * ($i + 1));
            clearstatcache(true, $path);
        }

        // Gravação direta no destino (sem rename).
        if (file_put_contents($path, $content, LOCK_EX) !== false) {
            @unlink($tempPath);
            clearstatcache(true, $path);

            return;
        }

        @unlink($tempPath);
        $hint = $lastError?->getMessage() ?: 'rename/copy falhou';
        throw new \RuntimeException('Falha ao substituir arquivo (Windows): '.$path.' — '.$hint);
    }

    /**
     * Esvazia pasta de views compiladas sem depender de unlink arquivo-a-arquivo travado.
     */
    public function clearCompiledViewsDirectory(string $viewsPath): void
    {
        $viewsPath = rtrim($viewsPath, '\\/');
        if ($viewsPath === '' || ! is_dir($viewsPath)) {
            if ($viewsPath !== '') {
                @mkdir($viewsPath, 0775, true);
            }

            return;
        }

        $parent = dirname($viewsPath);
        $trash = $parent.DIRECTORY_SEPARATOR.'views_trash_'.bin2hex(random_bytes(4));

        // Renomear o diretório inteiro evita code:5 em arquivos abertos pelo servidor.
        if (@rename($viewsPath, $trash)) {
            @mkdir($viewsPath, 0775, true);
            $this->deleteDirectoryBestEffort($trash);

            return;
        }

        // Fallback: limpa o que der dentro da pasta atual.
        foreach ($this->glob($viewsPath.DIRECTORY_SEPARATOR.'*') as $item) {
            if (is_dir($item)) {
                $this->deleteDirectoryBestEffort($item);
            } else {
                @chmod($item, 0666);
                @unlink($item);
            }
        }
    }

    private function deleteDirectoryBestEffort(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        try {
            $this->deleteDirectory($directory);
        } catch (Throwable) {
            // Melhor esforço: deixa a pasta trash para o SO/limpeza posterior.
        }
    }
}
