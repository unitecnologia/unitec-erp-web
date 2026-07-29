<?php

namespace Unitec\FiscalEngine\Util;

final class CaBundleResolver
{
    private static ?string $projectRoot = null;

    public static function setProjectRoot(?string $path): void
    {
        self::$projectRoot = $path !== null && $path !== '' ? rtrim($path, '/\\') : null;
    }

    public static function resolve(): ?string
    {
        foreach (self::candidatePaths() as $path) {
            if (is_file($path) && filesize($path) > 1024) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function candidatePaths(): array
    {
        $paths = [];

        // Bundle embutido no motor — funciona sem depender do php.ini do servidor.
        $paths[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'cacert.pem';

        foreach (['curl.cainfo', 'openssl.cafile'] as $iniKey) {
            $iniPath = trim((string) ini_get($iniKey));

            if ($iniPath !== '') {
                $paths[] = $iniPath;
            }
        }

        foreach (['FISCAL_ENGINE_CAINFO', 'SSL_CERT_FILE'] as $envKey) {
            $envPath = trim((string) getenv($envKey));

            if ($envPath !== '') {
                $paths[] = $envPath;
            }
        }

        foreach (self::rootsToSearch() as $root) {
            $paths[] = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem';
            $paths[] = $root . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'cacert.pem';
            $paths[] = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'guzzlehttp' . DIRECTORY_SEPARATOR . 'guzzle' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'cacert.pem';
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private static function rootsToSearch(): array
    {
        $roots = [];

        if (self::$projectRoot !== null) {
            $roots[] = self::$projectRoot;
        }

        $cwd = getcwd();

        if ($cwd !== false && $cwd !== '') {
            $roots[] = $cwd;
        }

        $packageSrc = dirname(__DIR__, 2);

        for ($current = $packageSrc, $depth = 0; $depth < 6; $depth++) {
            $roots[] = $current;
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return array_values(array_unique($roots));
    }
}
