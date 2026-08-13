<?php

namespace Unitec\FiscalEngine\Nfe;

use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\SslTransportOptions;
use Unitec\FiscalEngine\Util\XmlHelper;

final class DistribuicaoDfeSoapClient
{
    private const WSDL_NS = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe';

    public function distDfeInteresse(
        string $endpoint,
        string $xmlDistDfeInt,
        Certificate $certificate,
    ): string {
        $payload = XmlHelper::stripXmlDeclaration($xmlDistDfeInt);
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<nfeDistDFeInteresse xmlns="' . self::WSDL_NS . '">'
            . '<nfeDadosMsg>' . $payload . '</nfeDadosMsg>'
            . '</nfeDistDFeInteresse>'
            . '</soap12:Body>'
            . '</soap12:Envelope>';

        return $this->transmit(
            $endpoint,
            $envelope,
            $certificate,
            '"' . AnDistribuicaoDfeEndpoints::soapAction() . '"',
        );
    }

    /**
     * @internal Exposto para testes.
     */
    public function buildEnvelope(string $xmlPayload): string
    {
        $payload = XmlHelper::stripXmlDeclaration($xmlPayload);

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<nfeDistDFeInteresse xmlns="' . self::WSDL_NS . '">'
            . '<nfeDadosMsg>' . $payload . '</nfeDadosMsg>'
            . '</nfeDistDFeInteresse>'
            . '</soap12:Body>'
            . '</soap12:Envelope>';
    }

    private function transmit(string $endpoint, string $envelope, Certificate $certificate, string $action): string
    {
        $ch = curl_init($endpoint);

        if ($ch === false) {
            throw new FiscalEngineException('Não foi possível iniciar a comunicação com a Distribuição DF-e.');
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
                CURLOPT_TIMEOUT => 90,
                CURLOPT_CONNECTTIMEOUT => 30,
            ] + SslTransportOptions::curlOptions());

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new FiscalEngineException('Falha na comunicação com a Distribuição DF-e: ' . $error);
            }

            if ($httpCode !== 200) {
                $snippet = mb_substr(trim(strip_tags((string) $response)), 0, 180, 'UTF-8');

                throw new FiscalEngineException("Distribuição DF-e retornou HTTP {$httpCode}: {$snippet}");
            }

            return (string) $response;
        } finally {
            @unlink($tempCombined);
            @unlink($tempKey);
        }
    }
}
