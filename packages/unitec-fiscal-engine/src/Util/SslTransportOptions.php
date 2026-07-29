<?php

namespace Unitec\FiscalEngine\Util;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class SslTransportOptions
{
    /**
     * Opções cURL para HTTPS com a SEFAZ (servidor + certificado A1 do cliente).
     *
     * No Windows, o OpenSSL embutido no PHP costuma ignorar CURLOPT_CAINFO;
     * usamos o repositório nativo de CAs (CURLSSLOPT_NATIVE_CA).
     *
     * @return array<int, bool|int|string>
     */
    public static function curlOptions(): array
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caBundle = CaBundleResolver::resolve();
        $caContents = $caBundle !== null ? (string) file_get_contents($caBundle) : '';

        if (self::shouldUseNativeCaStore()) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }

        // Sempre anexa cacert.pem quando disponível: no Windows o store nativo
        // às vezes não cobre a cadeia do Ambiente Nacional (www.nfe.fazenda.gov.br).
        if ($caContents !== '') {
            if (defined('CURLOPT_CAINFO_BLOB')) {
                $options[CURLOPT_CAINFO_BLOB] = $caContents;
            } elseif ($caBundle !== null) {
                $options[CURLOPT_CAINFO] = $caBundle;
            }
        } elseif (! self::shouldUseNativeCaStore()) {
            throw new FiscalEngineException(
                'Bundle de certificados SSL (cacert.pem) não encontrado no motor fiscal.',
            );
        }

        return $options;
    }

    private static function shouldUseNativeCaStore(): bool
    {
        return PHP_OS_FAMILY === 'Windows'
            && defined('CURLOPT_SSL_OPTIONS')
            && defined('CURLSSLOPT_NATIVE_CA');
    }
}
