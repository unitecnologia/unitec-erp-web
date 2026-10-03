<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use Throwable;
use Unitec\FiscalEngine\Util\CaBundleResolver;
use Unitec\FiscalEngine\Util\SslTransportOptions;

class NfseIpmCliente
{
    public function url(Empresa $empresa): string
    {
        $ambiente = strtolower(trim((string) $empresa->nfse_ambiente));

        if (! NfseSefinAmbiente::tryFrom($ambiente) instanceof NfseSefinAmbiente) {
            throw new NfseNaoTransmitida('Selecione o ambiente da NFS-e.');
        }

        $url = trim((string) ($ambiente === NfseSefinAmbiente::Producao->value
            ? $empresa->nfse_url_producao
            : $empresa->nfse_url_homologacao));

        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            throw new NfseNaoTransmitida(
                $ambiente === NfseSefinAmbiente::Producao->value
                    ? 'Informe a URL de produção do WebService IPM.'
                    : 'Informe a URL de homologação do WebService IPM.',
            );
        }

        return $url;
    }

    public function enviar(Empresa $empresa, string $xmlRps): NfseIpmResposta
    {
        $usuario = trim((string) $empresa->nfse_ws_usuario);
        $senha = (string) $empresa->nfse_ws_senha;

        if ($usuario === '' || $senha === '') {
            throw new NfseNaoTransmitida('Informe o usuário e a senha do WebService IPM.');
        }

        $envelope = $this->envelope($xmlRps);
        $certificado = $this->certificado($empresa);
        $certPath = null;
        $keyPath = null;

        if ($certificado !== null) {
            $certPath = tempnam(sys_get_temp_dir(), 'nfse-ipm-cert-');
            $keyPath = tempnam(sys_get_temp_dir(), 'nfse-ipm-key-');

            if ($certPath === false || $keyPath === false) {
                throw new NfseNaoTransmitida('Não foi possível preparar o certificado para a transmissão.');
            }

            file_put_contents($certPath, $certificado['certificado']);
            file_put_contents($keyPath, $certificado['chave']);
        }

        try {
            CaBundleResolver::setProjectRoot(base_path());
            $ch = curl_init($this->url($empresa));

            if ($ch === false) {
                throw new NfseNaoTransmitida('Não foi possível abrir a conexão com o WebService IPM.');
            }

            $opcoes = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $envelope,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=utf-8',
                    'Accept: text/xml',
                    'SOAPAction: "http://nfse.abrasf.org.br/GerarNfse"',
                    'Authorization: Basic '.base64_encode($usuario.':'.$senha),
                ],
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 20,
            ] + SslTransportOptions::curlOptions();

            if ($certPath !== null && $keyPath !== null) {
                $opcoes[CURLOPT_SSLCERT] = $certPath;
                $opcoes[CURLOPT_SSLKEY] = $keyPath;
                $opcoes[CURLOPT_SSLCERTTYPE] = 'PEM';
                $opcoes[CURLOPT_SSLKEYTYPE] = 'PEM';
            }

            curl_setopt_array($ch, $opcoes);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erroCurl = curl_error($ch);
            curl_close($ch);
        } finally {
            if (is_string($certPath)) {
                @unlink($certPath);
            }

            if (is_string($keyPath)) {
                @unlink($keyPath);
            }
        }

        if ($body === false || $status === 0) {
            throw new NfseNaoTransmitida($erroCurl !== '' ? 'Não foi possível transmitir a NFS-e ao IPM.' : 'Não foi possível transmitir a NFS-e ao IPM.');
        }

        if ($status === 401 || $status === 403) {
            throw new NfseNaoTransmitida('O WebService IPM não aceitou o usuário ou a senha.');
        }

        $resposta = NfseIpmResposta::interpretar((string) $body);

        if ($status >= 500 && ! $resposta->autorizada && $resposta->erros === []) {
            throw new NfseNaoTransmitida('O WebService IPM não respondeu a emissão.');
        }

        return $resposta;
    }

    public function envelope(string $xmlRps): string
    {
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<cabecalho xmlns="'.NfseIpmXmlGerador::NS.'" versao="1.00"><versaoDados>2.04</versaoDados></cabecalho>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfse="http://nfse.abrasf.org.br">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .'<nfse:GerarNfse>'
            .'<nfseCabecMsg><![CDATA['.$cabecalho.']]></nfseCabecMsg>'
            .'<nfseDadosMsg><![CDATA['.$this->cdata($xmlRps).']]></nfseDadosMsg>'
            .'</nfse:GerarNfse>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @return array{certificado: string, chave: string}|null
     */
    private function certificado(Empresa $empresa): ?array
    {
        try {
            $parametros = VendasParametro::query()->where('empresa_id', $empresa->id)->first();

            if (! $parametros instanceof VendasParametro) {
                return null;
            }

            $certificado = NfceFiscalCertificateResolver::resolve($empresa, $parametros);

            if ($certificado->certificatePem === '' || $certificado->privateKeyPem === '') {
                return null;
            }

            return [
                'certificado' => $certificado->certificatePem,
                'chave' => $certificado->privateKeyPem,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function cdata(string $xml): string
    {
        $limpo = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $limpo = preg_replace('/<\?xml[^?]*\?>/', '', $limpo) ?? $limpo;

        return str_replace(']]>', ']]]]><![CDATA[>', trim($limpo));
    }
}
