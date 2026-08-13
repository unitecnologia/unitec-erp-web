<?php

namespace App\Support\Erp\Atualizacao;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Pasta atualizacao/ no cliente: arquivos soltos prontos para aplicar (sem ZIP).
 */
final class AtualizacaoPasta
{
    public static function root(?string $appPath = null): string
    {
        $base = $appPath ?: base_path();

        return rtrim($base, '\\/').DIRECTORY_SEPARATOR.'atualizacao';
    }

    public static function readyPath(?string $appPath = null): string
    {
        return self::root($appPath).DIRECTORY_SEPARATOR.'ready.json';
    }

    public static function filesRoot(?string $appPath = null): string
    {
        // Árvore fica direto em atualizacao/ (ready.json na raiz da pasta).
        return self::root($appPath);
    }

    /**
     * @return array{ready?: bool, version?: string, deposited_at?: string}|null
     */
    public static function readReady(?string $appPath = null): ?array
    {
        $path = self::readyPath($appPath);
        if (! is_file($path)) {
            return null;
        }

        try {
            /** @var array{ready?: bool, version?: string, deposited_at?: string} $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function pendingVersion(?string $appPath = null): ?string
    {
        $ready = self::readReady($appPath);
        if (! is_array($ready) || empty($ready['ready'])) {
            return null;
        }

        $version = trim((string) ($ready['version'] ?? ''));

        return $version !== '' ? $version : null;
    }

    public static function isPendingNewer(?string $appPath = null): bool
    {
        $pending = self::pendingVersion($appPath);
        if ($pending === null) {
            return false;
        }

        $installed = (string) config('unitec.versao', '');
        if ($installed === '') {
            return true;
        }

        return self::compareVersions($pending, $installed) > 0;
    }

    /**
     * @return int negative if a < b, 0 if equal, positive if a > b
     */
    public static function compareVersions(string $a, string $b): int
    {
        $pa = array_map('intval', preg_split('/\./', $a) ?: []);
        $pb = array_map('intval', preg_split('/\./', $b) ?: []);
        $len = max(count($pa), count($pb));

        for ($i = 0; $i < $len; $i++) {
            $va = $pa[$i] ?? 0;
            $vb = $pb[$i] ?? 0;
            if ($va !== $vb) {
                return $va <=> $vb;
            }
        }

        return 0;
    }

    public static function writeReady(string $version, ?string $appPath = null): void
    {
        $root = self::root($appPath);
        File::ensureDirectoryExists($root);

        $payload = [
            'ready' => true,
            'version' => $version,
            'deposited_at' => now()->toIso8601String(),
        ];

        File::put(self::readyPath($appPath), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function clear(?string $appPath = null): void
    {
        $root = self::root($appPath);
        if (! is_dir($root)) {
            return;
        }

        try {
            File::deleteDirectory($root);
        } catch (\Throwable $e) {
            Log::warning('Falha ao limpar atualizacao/', ['message' => $e->getMessage()]);
        }

        File::ensureDirectoryExists($root);
    }

    public static function hasArtisanTree(?string $appPath = null): bool
    {
        return is_file(self::filesRoot($appPath).DIRECTORY_SEPARATOR.'artisan')
            && is_file(self::filesRoot($appPath).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
    }
}
