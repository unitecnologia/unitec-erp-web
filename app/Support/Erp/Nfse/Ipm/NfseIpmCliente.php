<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unitec\FiscalEngine\Util\CaBundleResolver;
use Unitec\FiscalEngine\Util\SslTransportOptions;

class NfseIpmCliente
{
    public const SOAP_ACTION_GERAR_NFSE = 'net.atende#GerarNfseEnvio';

    public const SOAP_ACTION_CANCELAR_NFSE = 'net.atende#CancelarNfseEnvio';

    /**
     * A IPM não tem servidor de homologação: o teste usa a URL do município com EnvioTeste=1.
     */
    public function url(Empresa $empresa): string
    {
        $producao = ! $this->modoTeste($empresa);
        $url = trim((string) ($producao ? $empresa->nfse_url_producao : $empresa->nfse_url_homologacao));

        if (! $producao && $url === '') {
            $url = trim((string) $empresa->nfse_url_producao);
        }

        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            throw new NfseNaoTransmitida('Informe a URL do WebService IPM do município.');
        }

        $oficial = NfseIpmMunicipios::endpoint($empresa->cidade_codigo);

        if ($oficial !== null && strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) parse_url($oficial, PHP_URL_HOST)) !== 0) {
            throw new NfseNaoTransmitida('A URL do WebService IPM não é a do município do prestador. Use '.$oficial.' nas configurações fiscais.');
        }

        return preg_replace('/[?&]wsdl$/i', '', $url) ?? $url;
    }

    public function modoTeste(Empresa $empresa): bool
    {
        $ambiente = NfseSefinAmbiente::tryFrom(strtolower(trim((string) $empresa->nfse_ambiente)));

        if (! $ambiente instanceof NfseSefinAmbiente) {
            throw new NfseNaoTransmitida('Selecione o ambiente da NFS-e.');
        }

        return $ambiente !== NfseSefinAmbiente::Producao;
    }

    /**
     * Usuário da Basic Auth IPM: CNPJ da empresa, só dígitos. Null quando o CNPJ não é válido.
     */
    public static function usuarioDoCnpj(mixed $cnpj): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cnpj) ?? '';

        if (strlen($digitos) !== 14 || preg_match('/^(\d)\1{13}$/', $digitos) === 1) {
            return null;
        }

        foreach ([12, 13] as $posicao) {
            $soma = 0;

            for ($i = 0, $peso = $posicao - 7; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * $peso;
                $peso = $peso === 2 ? 9 : $peso - 1;
            }

            $resto = $soma % 11;

            if ((int) $digitos[$posicao] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return null;
            }
        }

        return $digitos;
    }

    public function usuario(Empresa $empresa): string
    {
        return self::usuarioDoCnpj($empresa->cnpj)
            ?? throw new NfseNaoTransmitida('O CNPJ da empresa está ausente ou inválido. Corrija o cadastro da empresa para transmitir pelo IPM.');
    }

    public function enviar(Empresa $empresa, string $xmlRps, bool $envioTeste = false): NfseIpmResposta
    {
        if ($envioTeste !== str_contains($xmlRps, '<EnvioTeste>1</EnvioTeste>')) {
            throw new NfseNaoTransmitida('O modo de envio do RPS não confere com o ambiente IPM configurado.');
        }

        [$body, $status] = $this->postar($empresa, $this->envelope($xmlRps), self::SOAP_ACTION_GERAR_NFSE);

        $resposta = NfseIpmResposta::interpretar($body, $envioTeste);

        if ($status >= 500 && ! $resposta->autorizada && $resposta->erros === []) {
            throw new NfseNaoTransmitida('O WebService IPM não respondeu a emissão.');
        }

        return $resposta;
    }

    public function cancelar(Empresa $empresa, string $xmlPedido): NfseIpmCancelamentoResposta
    {
        [$body] = $this->postar($empresa, $this->envelopeCancelamento($xmlPedido), self::SOAP_ACTION_CANCELAR_NFSE);

        return NfseIpmCancelamentoResposta::interpretar($body);
    }

    /**
     * Mesmo ajuste de namespace do GerarNfseEnvio: xmlns só no InfPedidoCancelamento.
     */
    public function envelopeCancelamento(string $xmlPedido): string
    {
        $corpo = $this->semDeclaracao($xmlPedido);
        $corpo = preg_replace(
            '#^<CancelarNfseEnvio xmlns="'.preg_quote(NfseIpmXmlGerador::NS, '#').'">#',
            '<CancelarNfseEnvio>',
            $corpo,
            1,
            $semNamespace,
        ) ?? $corpo;

        if ($semNamespace === 1) {
            $corpo = preg_replace(
                '#<InfPedidoCancelamento(?![^>]*\sxmlns=)#',
                '<InfPedidoCancelamento xmlns="'.NfseIpmXmlGerador::NS.'"',
                $corpo,
                1,
            ) ?? $corpo;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .$corpo
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function postar(Empresa $empresa, string $envelope, string $soapAction): array
    {
        $usuario = $this->usuario($empresa);
        $senha = (string) $empresa->nfse_ws_senha;

        if ($senha === '') {
            throw new NfseNaoTransmitida('Informe a senha do WebService IPM.');
        }

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
            $url = $this->url($empresa);
            $wwwAuthenticate = null;
            $ch = curl_init($url);

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
                    'SOAPAction: "'.$soapAction.'"',
                    'Authorization: Basic '.base64_encode($usuario.':'.$senha),
                ],
                CURLOPT_HEADERFUNCTION => function ($ch, string $linha) use (&$wwwAuthenticate): int {
                    if (stripos($linha, 'WWW-Authenticate:') === 0) {
                        $wwwAuthenticate = trim(substr($linha, 17));
                    }

                    return strlen($linha);
                },
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
            throw new NfseNaoTransmitida('Não foi possível comunicar com o WebService IPM.');
        }

        if ($status === 401 || $status === 403) {
            Log::warning('NFS-e IPM recusou a autenticação.', [
                'status' => $status,
                'url' => $url,
                'usuario' => substr($usuario, 0, 2).str_repeat('*', 8).substr($usuario, -4),
                'www_authenticate' => $wwwAuthenticate,
                'corpo' => mb_substr((string) $body, 0, 2000),
            ]);

            throw new NfseNaoTransmitida('O WebService IPM não aceitou o usuário ou a senha (HTTP '.$status.').');
        }

        return [(string) $body, $status];
    }

    /**
     * GerarNfseEnvio vai como elemento literal no Body (document/literal do WSDL net.atende), sem CDATA.
     * O servidor IPM recoloca o xmlns ABRASF no GerarNfseEnvio antes do XSD; se ele já vier, a IPM falha com
     * "Attribute xmlns redefined". Por isso, como nos exemplos oficiais, o namespace vai só no InfDeclaracaoPrestacaoServico.
     */
    public function envelope(string $xmlRps): string
    {
        $corpo = preg_replace(
            '#^<GerarNfseEnvio xmlns="'.preg_quote(NfseIpmXmlGerador::NS, '#').'">#',
            '<GerarNfseEnvio>',
            $this->semDeclaracao($xmlRps),
            1,
            $semNamespace,
        ) ?? $this->semDeclaracao($xmlRps);

        if ($semNamespace === 1) {
            $corpo = preg_replace(
                '#<InfDeclaracaoPrestacaoServico(?![^>]*\sxmlns=)#',
                '<InfDeclaracaoPrestacaoServico xmlns="'.NfseIpmXmlGerador::NS.'"',
                $corpo,
                1,
            ) ?? $corpo;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .$corpo
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

    private function semDeclaracao(string $xml): string
    {
        $limpo = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;

        return trim(preg_replace('/<\?xml[^?]*\?>/', '', $limpo) ?? $limpo);
    }
}
