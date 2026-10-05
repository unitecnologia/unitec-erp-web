<?php

namespace App\Support\Erp\Ccg;

use DOMDocument;
use Illuminate\Support\Facades\Log;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Util\SslTransportOptions;

/**
 * SOAP 1.2 + TLS 1.2 + certificado cliente, no mesmo padrão do motor fiscal (ScNfceSoapClient).
 */
final class CcgSoapCurlTransport implements CcgSoapTransport
{
    public function post(string $url, string $envelope, Certificate $certificate, int $timeoutSeconds): string
    {
        $timeoutSeconds = max(1, min(300, $timeoutSeconds));
        $ch = curl_init($url);

        if ($ch === false) {
            throw new CcgConsultaException(
                'Não foi possível iniciar a consulta GTIN na SVRS.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        $tempCombined = tempnam(sys_get_temp_dir(), 'ufe_pem_');
        $tempKey = tempnam(sys_get_temp_dir(), 'ufe_key_');

        if ($tempCombined === false || $tempKey === false) {
            curl_close($ch);

            throw new CcgConsultaException(
                'Falha ao preparar o certificado digital para a consulta GTIN.',
                CcgConsultaException::CERTIFICADO,
            );
        }

        file_put_contents($tempCombined, $certificate->privateKeyPem . $certificate->certificatePem);
        file_put_contents($tempKey, $certificate->privateKeyPem);

        $action = '"' . CcgConsGtinEndpoints::SOAP_ACTION . '"';

        try {
            $options = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $envelope,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/soap+xml; charset=utf-8; action=' . $action,
                    'Content-Length: ' . strlen($envelope),
                ],
                CURLOPT_SSLCERT => $tempCombined,
                CURLOPT_SSLKEY => $tempKey,
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => min(15, $timeoutSeconds),
                CURLOPT_ENCODING => '',
            ] + SslTransportOptions::curlOptions();

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false && self::isSslError($error) && self::allowsInsecureSslFallback($url)) {
                curl_setopt_array($ch, [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $response = curl_exec($ch);
                $error = curl_error($ch);
                $errno = curl_errno($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            }

            if ($response === false) {
                throw self::fromCurlError($error, $errno);
            }

            if (in_array($httpCode, [401, 403], true)) {
                throw new CcgConsultaException(
                    'Certificado digital rejeitado pela SVRS. Verifique se o certificado A1 da empresa está válido.',
                    CcgConsultaException::CERTIFICADO,
                );
            }

            if ($httpCode === 400 || $httpCode === 500) {
                $body = (string) $response;
                $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                self::logHttpFault($httpCode, $contentType, $error, $body);

                $fault = self::faultText($body);

                if ($fault !== null) {
                    throw new CcgConsultaException(
                        'HTTP ' . $httpCode . ': ' . $fault,
                        CcgConsultaException::INDISPONIVEL,
                    );
                }

                if (preg_match('/<(?:\w+:)?cStat\b/', $body) === 1) {
                    return $body;
                }
            }

            if ($httpCode !== 200) {
                throw new CcgConsultaException(
                    'Serviço de consulta GTIN da SVRS indisponível (HTTP ' . $httpCode . '). Tente novamente mais tarde.',
                    CcgConsultaException::INDISPONIVEL,
                );
            }

            return (string) $response;
        } finally {
            if ($ch instanceof \CurlHandle || is_resource($ch)) {
                curl_close($ch);
            }

            @unlink($tempCombined);
            @unlink($tempKey);
        }
    }

    private static function logHttpFault(int $httpCode, string $contentType, string $curlError, string $body): void
    {
        Log::warning('CCG ccgConsGTIN resposta HTTP ' . $httpCode, [
            'http_status' => $httpCode,
            'content_type' => $contentType !== '' ? $contentType : null,
            'curl_error' => $curlError !== '' ? $curlError : null,
            'body' => self::bodyForLog($body),
        ]);
    }

    private static function faultText(string $body): ?string
    {
        if (! str_contains(strtolower($body), 'fault')) {
            return null;
        }

        $document = new DOMDocument();

        if (@$document->loadXML($body) !== true) {
            return null;
        }

        return CcgConsGtinClient::soapFaultText($document);
    }

    private static function bodyForLog(string $body): string
    {
        $redacted = preg_replace(
            '/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s',
            '[conteudo de certificado removido]',
            $body,
        ) ?? $body;

        if (strlen($redacted) > 20000) {
            return substr($redacted, 0, 20000) . "\n[truncado]";
        }

        return $redacted;
    }

    private static function fromCurlError(string $error, int $errno): CcgConsultaException
    {
        $normalized = strtolower($error);

        if ($errno === 28 || str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout')) {
            return new CcgConsultaException(
                'A consulta GTIN na SVRS excedeu o tempo limite.',
                CcgConsultaException::TIMEOUT,
            );
        }

        if (
            in_array($errno, [58, 59, 77, 83], true)
            || str_contains($normalized, 'local certificate')
            || str_contains($normalized, 'unable to load')
            || str_contains($normalized, 'bad certificate')
        ) {
            return new CcgConsultaException(
                'Certificado digital inválido na conexão com a SVRS. Verifique o certificado A1 da empresa.',
                CcgConsultaException::CERTIFICADO,
            );
        }

        if ($errno === 35 || $errno === 51 || $errno === 60 || str_contains($normalized, 'ssl') || str_contains($normalized, 'certificate')) {
            return new CcgConsultaException(
                'Falha de certificado ou TLS ao consultar a SVRS. Verifique o certificado A1 da empresa.',
                CcgConsultaException::CERTIFICADO,
            );
        }

        return new CcgConsultaException(
            'Serviço de consulta GTIN da SVRS indisponível. Tente novamente mais tarde.',
            CcgConsultaException::INDISPONIVEL,
        );
    }

    private static function isSslError(string $error): bool
    {
        $normalized = strtolower($error);

        return str_contains($normalized, 'ssl') || str_contains($normalized, 'certificate');
    }

    private static function allowsInsecureSslFallback(string $endpoint): bool
    {
        $host = strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?? ''));

        return str_ends_with($host, 'svrs.rs.gov.br')
            || str_ends_with($host, 'nfe.fazenda.gov.br')
            || str_ends_with($host, 'sef.sc.gov.br');
    }
}
