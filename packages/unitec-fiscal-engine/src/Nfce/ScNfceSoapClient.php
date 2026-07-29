<?php

namespace Unitec\FiscalEngine\Nfce;

use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\SslTransportOptions;
use Unitec\FiscalEngine\Util\XmlHelper;

final class ScNfceSoapClient
{
    private const WSDL_NS_AUTORIZACAO = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4';

    private const WSDL_NS_RECEPCAO_EVENTO = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4';

    private const WSDL_NS_CONSULTA_PROTOCOLO = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4';

    private const WSDL_NS_INUTILIZACAO = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeInutilizacao4';

    public function autorizar(
        string $endpoint,
        string $xmlEnviNfe,
        Certificate $certificate,
        string $cUf,
        string $versaoDados = '4.00',
    ): string {
        $payload = XmlHelper::stripXmlDeclaration($xmlEnviNfe);
        $envelope = $this->buildEnvelope(
            $payload,
            $cUf,
            $versaoDados,
            self::WSDL_NS_AUTORIZACAO,
        );

        return $this->transmit(
            $endpoint,
            $envelope,
            $certificate,
            '"' . ScNfceEndpoints::soapActionAutorizacao() . '"',
        );
    }

    public function recepcaoEvento(
        string $endpoint,
        string $xmlEnvEvento,
        Certificate $certificate,
        string $cUf,
        string $versaoDados = '1.00',
    ): string {
        $payload = XmlHelper::stripXmlDeclaration($xmlEnvEvento);
        $envelope = $this->buildEnvelope(
            $payload,
            $cUf,
            $versaoDados,
            self::WSDL_NS_RECEPCAO_EVENTO,
        );

        return $this->transmit(
            $endpoint,
            $envelope,
            $certificate,
            '"' . ScNfceEndpoints::soapActionRecepcaoEvento() . '"',
        );
    }

    public function consultaProtocolo(
        string $endpoint,
        string $xmlConsSitNfe,
        Certificate $certificate,
        string $cUf,
        string $versaoDados = '4.00',
    ): string {
        $payload = XmlHelper::stripXmlDeclaration($xmlConsSitNfe);
        $envelope = $this->buildEnvelope(
            $payload,
            $cUf,
            $versaoDados,
            self::WSDL_NS_CONSULTA_PROTOCOLO,
        );

        return $this->transmit(
            $endpoint,
            $envelope,
            $certificate,
            '"' . ScNfceEndpoints::soapActionConsultaProtocolo() . '"',
        );
    }

    public function inutilizacao(
        string $endpoint,
        string $xmlInutNfe,
        Certificate $certificate,
        string $cUf,
        string $versaoDados = '4.00',
    ): string {
        $payload = XmlHelper::stripXmlDeclaration($xmlInutNfe);
        $envelope = $this->buildEnvelope(
            $payload,
            $cUf,
            $versaoDados,
            self::WSDL_NS_INUTILIZACAO,
        );

        return $this->transmit(
            $endpoint,
            $envelope,
            $certificate,
            '"' . ScNfceEndpoints::soapActionInutilizacao() . '"',
        );
    }

    /**
     * @internal Exposto para validação do envelope em testes.
     */
    public function buildAutorizacaoEnvelope(string $xmlPayload, string $cUf, string $versaoDados = '4.00'): string
    {
        return $this->buildEnvelope(
            XmlHelper::stripXmlDeclaration($xmlPayload),
            $cUf,
            $versaoDados,
            self::WSDL_NS_AUTORIZACAO,
        );
    }

    public function buildRecepcaoEventoEnvelope(string $xmlPayload, string $cUf, string $versaoDados = '1.00'): string
    {
        return $this->buildEnvelope(
            XmlHelper::stripXmlDeclaration($xmlPayload),
            $cUf,
            $versaoDados,
            self::WSDL_NS_RECEPCAO_EVENTO,
        );
    }

    private function buildEnvelope(string $xmlPayload, string $cUf, string $versaoDados, string $wsdlNs): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Header>'
            . '<nfeCabecMsg xmlns="' . $wsdlNs . '">'
            . '<cUF>' . $cUf . '</cUF>'
            . '<versaoDados>' . $versaoDados . '</versaoDados>'
            . '</nfeCabecMsg>'
            . '</soap12:Header>'
            . '<soap12:Body>'
            . '<nfeDadosMsg xmlns="' . $wsdlNs . '">'
            . $xmlPayload
            . '</nfeDadosMsg>'
            . '</soap12:Body>'
            . '</soap12:Envelope>';
    }

    private function transmit(string $endpoint, string $envelope, Certificate $certificate, string $action): string
    {
        $ch = curl_init($endpoint);

        if ($ch === false) {
            throw new FiscalEngineException('Não foi possível iniciar a comunicação com a SEFAZ.');
        }

        $tempCombined = tempnam(sys_get_temp_dir(), 'ufe_pem_');
        $tempKey = tempnam(sys_get_temp_dir(), 'ufe_key_');

        if ($tempCombined === false || $tempKey === false) {
            throw new FiscalEngineException('Falha ao preparar certificado para transmissão.');
        }

        file_put_contents($tempCombined, $certificate->privateKeyPem . $certificate->certificatePem);
        file_put_contents($tempKey, $certificate->privateKeyPem);

        try {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $envelope,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/soap+xml; charset=utf-8; action=' . $action,
                    'Content-length: ' . strlen($envelope),
                ],
                CURLOPT_SSLCERT => $tempCombined,
                CURLOPT_SSLKEY => $tempKey,
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
            ] + SslTransportOptions::curlOptions());

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Ambiente Nacional (www.nfe.fazenda.gov.br) usa cadeia Let's Encrypt YR
            // ainda ausente em vários trust stores/Windows — retenta sem verify.
            if ($response === false && self::isSslError($error) && self::allowsInsecureSslFallback($endpoint)) {
                curl_setopt_array($ch, [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $response = curl_exec($ch);
                $error = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            }

            curl_close($ch);

            if ($response === false) {
                throw new FiscalEngineException('Falha na comunicação com a SEFAZ: ' . self::formatCurlError($error));
            }

            if ($httpCode !== 200) {
                throw new FiscalEngineException(self::formatHttpError($httpCode, (string) $response));
            }

            return (string) $response;
        } finally {
            @unlink($tempCombined);
            @unlink($tempKey);
        }
    }

    private static function isSslError(string $error): bool
    {
        $normalized = strtolower($error);

        return str_contains($normalized, 'ssl') || str_contains($normalized, 'certificate');
    }

    private static function allowsInsecureSslFallback(string $endpoint): bool
    {
        $host = strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?? ''));

        return str_ends_with($host, 'nfe.fazenda.gov.br')
            || str_ends_with($host, 'svrs.rs.gov.br')
            || str_ends_with($host, 'sef.sc.gov.br');
    }

    private static function formatHttpError(int $httpCode, string $response): string
    {
        $snippet = '';

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $response, $matches) === 1) {
            $snippet = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ($snippet === '') {
            $snippet = trim(strip_tags($response));
            $snippet = preg_replace('/\{[^}]*\}/', ' ', $snippet) ?? $snippet;
            $snippet = preg_replace('/\b(body|p|h\d)\b/i', ' ', $snippet) ?? $snippet;
        }

        $snippet = trim(preg_replace('/\s+/', ' ', $snippet) ?? '');
        $snippet = mb_substr($snippet, 0, 180, 'UTF-8');

        if ($snippet !== '') {
            return "SEFAZ retornou HTTP {$httpCode}: {$snippet}";
        }

        return "SEFAZ retornou HTTP {$httpCode}.";
    }

    private static function formatCurlError(string $error): string
    {
        $normalized = strtolower($error);

        if (str_contains($normalized, 'could not resolve host')) {
            return 'não foi possível resolver o endereço do webservice da SEFAZ (verifique DNS/internet).';
        }

        if (str_contains($normalized, 'ssl') || str_contains($normalized, 'certificate')) {
            return 'falha SSL ao conectar na SEFAZ (' . $error . ').';
        }

        return $error;
    }
}
