<?php

namespace Unitec\FiscalEngine\Certificate;

use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\NumberFormatter;

final class CertificateLoader
{
    public static function fromPkcs12(string $content, string $password, ?string $cnpjFallback = null): Certificate
    {
        $certs = [];

        if (! openssl_pkcs12_read($content, $certs, $password)) {
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
