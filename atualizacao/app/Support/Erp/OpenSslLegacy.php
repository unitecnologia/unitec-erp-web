<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Process;

/**
 * Garante OpenSSL 3 com provider legacy para ler .pfx A1 antigos (RC2-40 / 3DES).
 * No Windows o PHP embutido precisa de OPENSSL_MODULES apontando para legacy.dll
 * desde o início do processo — por isso a leitura crítica usa um subprocesso.
 */
final class OpenSslLegacy
{
    private static bool $configured = false;

    private const CNF_BODY = <<<'CNF'
# Unitec ERP — OpenSSL 3 + legacy (PFX A1 antigo / RC2-40)
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
CNF;

    public static function ensure(): void
    {
        if (self::$configured) {
            return;
        }

        self::$configured = true;

        foreach (self::environment() as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @return array{OPENSSL_CONF?: string, OPENSSL_MODULES?: string}
     */
    public static function environment(): array
    {
        $env = [];

        $modulesDir = self::resolveModulesDirectory();
        if ($modulesDir !== null) {
            $env['OPENSSL_MODULES'] = $modulesDir;
        }

        $configPath = self::resolveOrCreateConfigPath();
        if ($configPath !== null) {
            $env['OPENSSL_CONF'] = $configPath;
        }

        return $env;
    }

    /**
     * Lê PKCS#12 via subprocesso PHP com OPENSSL_MODULES já definido.
     *
     * @return array{ok: bool, certs?: array{cert?: string, pkey?: string, extracerts?: mixed}, error?: string}
     */
    public static function readPkcs12ViaSubprocess(string $content, string $password): array
    {
        self::ensure();

        $env = self::environment();
        if (($env['OPENSSL_MODULES'] ?? '') === '') {
            return ['ok' => false, 'error' => 'legacy.dll ausente (OPENSSL_MODULES).'];
        }

        $tmpDir = storage_path('app/private/openssl-tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $pfxPath = $tmpDir.DIRECTORY_SEPARATOR.'read-'.uniqid('', true).'.pfx';
        $outPath = $tmpDir.DIRECTORY_SEPARATOR.'out-'.uniqid('', true).'.json';

        try {
            if (@file_put_contents($pfxPath, $content) === false) {
                return ['ok' => false, 'error' => 'Falha ao gravar .pfx temporário.'];
            }

            $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
            $reader = base_path('scripts/openssl-pkcs12-read.php');
            if (! is_file($reader)) {
                return ['ok' => false, 'error' => 'scripts/openssl-pkcs12-read.php ausente.'];
            }

            $result = Process::env(array_merge($env, [
                'UNITEC_PFX_PASSWORD' => $password,
            ]))->timeout(30)->run([
                $php,
                $reader,
                $pfxPath,
                $outPath,
            ]);

            if (! $result->successful()) {
                $stderr = trim($result->errorOutput()."\n".$result->output());
                if (is_file($outPath)) {
                    /** @var mixed $decoded */
                    $decoded = json_decode((string) file_get_contents($outPath), true);
                    if (is_array($decoded) && ! ($decoded['ok'] ?? false)) {
                        return ['ok' => false, 'error' => (string) ($decoded['error'] ?? $stderr)];
                    }
                }

                return ['ok' => false, 'error' => $stderr !== '' ? $stderr : 'Subprocesso OpenSSL falhou.'];
            }

            if (! is_file($outPath)) {
                return ['ok' => false, 'error' => 'Saída do subprocesso ausente.'];
            }

            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($outPath), true);
            if (! is_array($decoded)) {
                return ['ok' => false, 'error' => 'JSON inválido do subprocesso.'];
            }

            if (! ($decoded['ok'] ?? false)) {
                return ['ok' => false, 'error' => (string) ($decoded['error'] ?? 'Falha ao ler PKCS#12.')];
            }

            return [
                'ok' => true,
                'certs' => [
                    'cert' => (string) ($decoded['cert'] ?? ''),
                    'pkey' => (string) ($decoded['pkey'] ?? ''),
                    'extracerts' => $decoded['extracerts'] ?? null,
                ],
            ];
        } finally {
            @unlink($pfxPath);
            @unlink($outPath);
        }
    }

    public static function resolveModulesDirectory(): ?string
    {
        $candidates = [
            resource_path('ssl/openssl'),
            base_path('tools/php/extras/ssl'),
            base_path('tools/php/ossl-modules'),
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl',
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'ossl-modules',
        ];

        foreach ($candidates as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            if (is_file($dir.DIRECTORY_SEPARATOR.'legacy.dll')
                || is_file($dir.DIRECTORY_SEPARATOR.'legacy.so')
                || is_file($dir.DIRECTORY_SEPARATOR.'legacy.dylib')) {
                return $dir;
            }
        }

        return null;
    }

    public static function resolveOrCreateConfigPath(): ?string
    {
        $candidates = [
            resource_path('ssl/openssl/openssl-unitec.cnf'),
            base_path('tools/php/extras/ssl/openssl-unitec.cnf'),
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl-unitec.cnf',
            storage_path('app/private/openssl-unitec.cnf'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 32) {
                return $candidate;
            }
        }

        $writable = storage_path('app/private/openssl-unitec.cnf');
        $dir = dirname($writable);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (@file_put_contents($writable, self::CNF_BODY) === false) {
            return null;
        }

        return $writable;
    }

    public static function lastError(): string
    {
        $last = '';
        while (($e = openssl_error_string()) !== false) {
            $last = $e;
        }

        return $last;
    }
}
