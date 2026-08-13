<?php

namespace Unitec\FiscalEngine\Certificate;

final class CnpjExtractor
{
    /** OID 2.16.76.1.3.3 (CNPJ ICP-Brasil) em DER hexadecimal. */
    private const OID_CNPJ_HEX = '6086480186f8420303';

    public static function fromCertificatePem(string $certificatePem, array $parsed): string
    {
        $cnpj = self::fromSubject($parsed['subject'] ?? []);

        if ($cnpj !== '') {
            return $cnpj;
        }

        $cnpj = self::fromExtensions($parsed['extensions'] ?? []);

        if ($cnpj !== '') {
            return $cnpj;
        }

        return self::fromOidDer($certificatePem);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private static function fromSubject(array $subject): string
    {
        foreach ($subject as $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $cnpj = self::digits14((string) $item);

                    if ($cnpj !== '') {
                        return $cnpj;
                    }
                }

                continue;
            }

            $cnpj = self::digits14((string) $value);

            if ($cnpj !== '') {
                return $cnpj;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $extensions
     */
    private static function fromExtensions(array $extensions): string
    {
        foreach ($extensions as $name => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (str_contains((string) $name, '2.16.76.1.3.3') || str_contains($value, '2.16.76.1.3.3')) {
                $cnpj = self::digits14($value);

                if ($cnpj !== '') {
                    return $cnpj;
                }
            }
        }

        return '';
    }

    private static function fromOidDer(string $certificatePem): string
    {
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificatePem) ?? '';
        $der = base64_decode($body, true);

        if ($der === false) {
            return '';
        }

        $hex = bin2hex($der);
        $pos = stripos($hex, self::OID_CNPJ_HEX);

        if ($pos === false) {
            return '';
        }

        // Após o OID, o CNPJ costuma aparecer como 14 dígitos ASCII na estrutura ASN.1.
        $offset = $pos + strlen(self::OID_CNPJ_HEX);
        $snippet = substr($hex, $offset, 80);
        $ascii = '';

        for ($i = 0; $i < strlen($snippet) - 1; $i += 2) {
            $byte = hexdec(substr($snippet, $i, 2));

            if ($byte >= 48 && $byte <= 57) {
                $ascii .= chr($byte);
            } elseif ($ascii !== '' && strlen($ascii) < 14) {
                $ascii = '';
            }

            if (strlen($ascii) === 14) {
                return $ascii;
            }
        }

        // Fallback: varrer todo o certificado por sequência de 14 dígitos após o OID.
        $afterOid = substr($der, (int) ($pos / 2) + 10);
        if (preg_match('/(\d{14})/', $afterOid, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private static function digits14(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 14 ? $digits : '';
    }
}
