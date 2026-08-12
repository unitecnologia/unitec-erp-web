<?php

namespace Unitec\FiscalEngine\Certificate;

use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\NumberFormatter;

final class CertificateLoader
{
    public static function fromPkcs12(string $content, string $password, ?string $cnpjFallback = null): Certificate
    {
        if (class_exists(\App\Support\Erp\OpenSslLegacy::class)) {
            \App\Support\Erp\OpenSslLegacy::ensure();
        }

        $certs = [];

        while (function_exists('openssl_error_string') && openssl_error_string() !== false) {
        }

        $ok = @openssl_pkcs12_read($content, $certs, $password);

        if (! $ok && class_exists(\App\Support\Erp\OpenSslLegacy::class)) {
            $err = \App\Support\Erp\OpenSslLegacy::lastError();
            $needsLegacy = $err !== '' && (
                str_contains(mb_strtolower($err), 'unsupported')
                || str_contains(mb_strtolower($err), 'legacy')
                || str_contains(mb_strtolower($err), 'digital envelope')
            );

            if ($needsLegacy) {
                $viaSub = \App\Support\Erp\OpenSslLegacy::readPkcs12ViaSubprocess($content, $password);
                if ($viaSub['ok'] ?? false) {
                    $certs = $viaSub['certs'] ?? [];
                    $ok = true;
                }
            }
        }

        if (! $ok) {
            throw new FiscalEngineException('Não foi possível ler o certificado digital (.pfx). Verifique a senha.');
        }

        $privateKey = (string) ($certs['pkey'] ?? '');
        $certificate = (string) ($certs['cert'] ?? '');

        if ($privateKey === '' || $certificate === '') {
            throw new FiscalEngineException('Certificado digital incompleto (chave ou certificado ausente).');
        }

        $parsed = openssl_x509_parse($certificate);

        if ($parsed === false) {
            throw new FiscalEngineException('Não foi possível interpretar o certificado digital.');
        }

        $cnpj = CnpjExtractor::fromCertificatePem($certificate, $parsed);

        if ($cnpj === '' && $cnpjFallback !== null && $cnpjFallback !== '') {
            $cnpj = NumberFormatter::onlyDigits($cnpjFallback);
        }

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ não encontrado no certificado digital.');
        }

        return new Certificate($privateKey, $certificate, $cnpj);
    }

    public static function fromPkcs12File(string $path, string $password, ?string $cnpjFallback = null): Certificate
    {
        if (! is_readable($path)) {
            throw new FiscalEngineException("Arquivo de certificado não encontrado: {$path}");
        }

        return self::fromPkcs12((string) file_get_contents($path), $password, $cnpjFallback);
    }
}
