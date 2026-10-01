<?php

namespace App\Services\Sicredi;

use App\Models\Empresa;
use RuntimeException;

/**
 * Cliente HTTP da API de Cobrança Sicredi (manual 3.9.1).
 */
final class SicrediCobrancaClient
{
    public function __construct(private readonly SicrediCobrancaAuth $auth)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function gerarBoleto(Empresa $empresa, array $payload): array
    {
        $response = SicrediHttp::client()
            ->withHeaders($this->headers($empresa))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->post($this->auth->hostForEmpresa($empresa).'/cobranca/boleto/v1/boletos', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao gerar boleto Sicredi (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta Sicredi de geração de boleto inválida.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function baixarBoleto(Empresa $empresa, string $nossoNumero): array
    {
        $nossoNumero = $this->assertNossoNumero($nossoNumero);

        $response = SicrediHttp::client()
            ->withHeaders($this->headers($empresa))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->patch(
                $this->auth->hostForEmpresa($empresa)
                    .'/cobranca/boleto/v1/boletos/'.rawurlencode($nossoNumero).'/baixa',
                (object) []
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao baixar boleto Sicredi (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if ($json === null && $response->body() === '') {
            return ['ok' => true];
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta Sicredi de baixa inválida.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function alterarVencimento(Empresa $empresa, string $nossoNumero, string $dataVencimentoYmd): array
    {
        $nossoNumero = $this->assertNossoNumero($nossoNumero);
        $data = trim($dataVencimentoYmd);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new RuntimeException('Data de vencimento Sicredi inválida (use YYYY-MM-DD).');
        }

        $response = SicrediHttp::client()
            ->withHeaders($this->headers($empresa))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->patch(
                $this->auth->hostForEmpresa($empresa)
                    .'/cobranca/boleto/v1/boletos/'.rawurlencode($nossoNumero).'/data-vencimento',
                ['dataVencimento' => $data]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao alterar vencimento Sicredi (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if ($json === null && $response->body() === '') {
            return ['ok' => true];
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta Sicredi de alteração de vencimento inválida.');
        }

        return $json;
    }

    /**
     * PDF oficial do boleto (GET /boletos/pdf?linhaDigitavel=).
     *
     * @throws RuntimeException
     */
    public function pdfBoleto(Empresa $empresa, string $linhaDigitavel): string
    {
        $linha = preg_replace('/\D/', '', $linhaDigitavel) ?: '';
        if (strlen($linha) !== 47) {
            throw new RuntimeException('Linha digitável Sicredi inválida para PDF (precisa de 47 dígitos).');
        }

        $response = SicrediHttp::client()
            ->withHeaders($this->headers($empresa))
            ->timeout(60)
            ->accept('*/*')
            ->get(
                $this->auth->hostForEmpresa($empresa).'/cobranca/boleto/v1/boletos/pdf',
                ['linhaDigitavel' => $linha]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao obter PDF Sicredi (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $body = $response->body();
        if ($body === '' || str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '<')) {
            throw new RuntimeException('Resposta Sicredi de PDF inválida.');
        }

        return $body;
    }

    /**
     * Instrução sob demanda — preparado para UI futura.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function pedirProtesto(Empresa $empresa, string $nossoNumero): array
    {
        return $this->patchInstrucao($empresa, $nossoNumero, 'protesto');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function incluirNegativacao(Empresa $empresa, string $nossoNumero): array
    {
        return $this->patchInstrucao($empresa, $nossoNumero, 'negativacao');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function sustarProtestoBaixar(Empresa $empresa, string $nossoNumero): array
    {
        return $this->patchInstrucao($empresa, $nossoNumero, 'sustar-protesto-baixar-titulo');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function sustarProtestoManter(Empresa $empresa, string $nossoNumero): array
    {
        return $this->patchInstrucao($empresa, $nossoNumero, 'sustar-protesto-manter-titulo');
    }

    /**
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    private function headers(Empresa $empresa): array
    {
        $creds = $this->auth->credentialsForCobranca($empresa);

        return [
            'Authorization' => 'Bearer '.$creds['access_token'],
            'x-api-key' => $creds['x_api_key'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'cooperativa' => $this->auth->cooperativa($empresa),
            'posto' => $this->auth->posto($empresa),
            'codigoBeneficiario' => $this->auth->codigoBeneficiario($empresa),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function patchInstrucao(Empresa $empresa, string $nossoNumero, string $pathSuffix): array
    {
        $nossoNumero = $this->assertNossoNumero($nossoNumero);

        $response = SicrediHttp::client()
            ->withHeaders($this->headers($empresa))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->patch(
                $this->auth->hostForEmpresa($empresa)
                    .'/cobranca/boleto/v1/boletos/'.rawurlencode($nossoNumero).'/'.$pathSuffix,
                (object) []
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha na instrução Sicredi '.$pathSuffix.' (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if ($json === null && $response->body() === '') {
            return ['ok' => true];
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta Sicredi de instrução inválida ('.$pathSuffix.').');
        }

        return $json;
    }

    /**
     * @throws RuntimeException
     */
    private function assertNossoNumero(string $nossoNumero): string
    {
        $digits = preg_replace('/\D/', '', $nossoNumero) ?? '';
        if ($digits === '') {
            throw new RuntimeException('Nosso número Sicredi vazio.');
        }

        return $digits;
    }
}
