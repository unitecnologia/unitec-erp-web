<?php

namespace App\Support\Erp\Nfse;

use Unitec\FiscalEngine\Util\CaBundleResolver;
use Unitec\FiscalEngine\Util\SslTransportOptions;

class NfseSefinHttp implements NfseSefinEnvio
{
    public function enviar(NfseSefinRequisicao $requisicao): NfseSefinResposta
    {
        $host = strtolower((string) parse_url($requisicao->url, PHP_URL_HOST));

        if ($host === '' || ! NfseSefinEndpoints::urlOficial($requisicao->url)) {
            throw new NfseNaoTransmitida('O host da SEFIN Nacional não está habilitado para esta transmissão.');
        }

        if ($requisicao->metodo !== 'POST') {
            throw new NfseNaoTransmitida('A emissão da DPS só aceita POST.');
        }

        $certPath = tempnam(sys_get_temp_dir(), 'nfse-cert-');
        $keyPath = tempnam(sys_get_temp_dir(), 'nfse-key-');

        if ($certPath === false || $keyPath === false) {
            throw new NfseNaoTransmitida('Não foi possível preparar o certificado para a transmissão.');
        }

        file_put_contents($certPath, $requisicao->mtls['certificado']);
        file_put_contents($keyPath, $requisicao->mtls['chave_privada']);

        try {
            CaBundleResolver::setProjectRoot(base_path());
            $ch = curl_init($requisicao->url);

            if ($ch === false) {
                throw new NfseNaoTransmitida('Não foi possível abrir a conexão com a SEFIN Nacional.');
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requisicao->corpo,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_SSLCERT => $certPath,
                CURLOPT_SSLKEY => $keyPath,
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 20,
            ] + SslTransportOptions::curlOptions());

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } finally {
            @unlink($certPath);
            @unlink($keyPath);
        }

        if ($body === false || $status === 0) {
            throw new NfseNaoTransmitida('Não foi possível transmitir a DPS.');
        }

        return NfseSefinResposta::interpretar($status, (string) $body);
    }
}
