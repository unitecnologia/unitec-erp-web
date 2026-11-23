<?php

namespace App\Support\Erp\Nfse;

use Unitec\FiscalEngine\Util\CaBundleResolver;
use Unitec\FiscalEngine\Util\SslTransportOptions;

class NfseSefinHttp implements NfseSefinEnvio
{
    public function enviar(NfseSefinRequisicao $requisicao): NfseSefinResposta
    {
        [$status, $body] = $this->executar($requisicao);

        return NfseSefinResposta::interpretar($status, $body);
    }

    /**
     * Transporte mTLS bruto (emissão, eventos e consultas) sem interpretar o JSON.
     *
     * @return array{0: int, 1: string}
     */
    public function executar(NfseSefinRequisicao $requisicao): array
    {
        $host = strtolower((string) parse_url($requisicao->url, PHP_URL_HOST));

        if ($host === '' || ! NfseSefinEndpoints::urlOficial($requisicao->url)) {
            throw new NfseNaoTransmitida('O host da SEFIN Nacional não está habilitado para esta transmissão.');
        }

        $metodo = strtoupper($requisicao->metodo);

        if (! in_array($metodo, ['POST', 'GET'], true)) {
            throw new NfseNaoTransmitida('A SEFIN Nacional só aceita GET ou POST nesta integração.');
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

            $opcoes = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $this->headers($requisicao),
                CURLOPT_SSLCERT => $certPath,
                CURLOPT_SSLKEY => $keyPath,
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => $metodo,
            ] + SslTransportOptions::curlOptions();

            if ($metodo === 'POST') {
                $opcoes[CURLOPT_POST] = true;
                $opcoes[CURLOPT_POSTFIELDS] = $requisicao->corpo;
            } else {
                $opcoes[CURLOPT_HTTPGET] = true;
            }

            curl_setopt_array($ch, $opcoes);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erroCurl = curl_error($ch);
            curl_close($ch);
        } finally {
            @unlink($certPath);
            @unlink($keyPath);
        }

        if ($body === false || $status === 0) {
            $detalhe = trim($erroCurl ?? '');

            throw new NfseNaoTransmitida(
                $detalhe !== ''
                    ? 'Não foi possível comunicar com a SEFIN Nacional ('.$detalhe.').'
                    : 'Não foi possível comunicar com a SEFIN Nacional.'
            );
        }

        return [$status, (string) $body];
    }

    /**
     * @return list<string>
     */
    private function headers(NfseSefinRequisicao $requisicao): array
    {
        $linhas = [];

        foreach ($requisicao->headers as $nome => $valor) {
            $linhas[] = $nome.': '.$valor;
        }

        if ($linhas === []) {
            $linhas[] = 'Accept: application/json';
        }

        return $linhas;
    }
}
