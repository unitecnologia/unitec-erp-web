<?php

namespace App\Services\Ailos;

use App\Models\Empresa;
use RuntimeException;

/**
 * Cliente HTTP da API de Cobrança Ailos (boleto único / consulta / instruções).
 */
final class AilosCobrancaClient
{
    public function __construct(private readonly AilosCobrancaAuth $auth)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function gerarBoletoUnico(Empresa $empresa, array $payload): array
    {
        $convenio = $this->convenio($empresa);
        $creds = $this->auth->credentialsForCobranca($empresa);

        $response = AilosHttp::client()
            ->withHeaders($this->headers($creds))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->post(
                $this->auth->hostForEmpresa($empresa)
                    .'/ailos/cobranca/api/v2/boletos/gerar/boleto/convenios/'.$convenio,
                $payload
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->formatGeracaoError($response->status(), $response->body(), $convenio)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta Ailos de geração de boleto inválida.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function consultarBoleto(Empresa $empresa, int|string $numeroBoleto): array
    {
        $convenio = $this->convenio($empresa);
        $creds = $this->auth->credentialsForCobranca($empresa);

        $response = AilosHttp::client()
            ->withHeaders($this->headers($creds))
            ->timeout(45)
            ->acceptJson()
            ->get(
                $this->auth->hostForEmpresa($empresa)
                    .'/ailos/cobranca/api/v2/boletos/consultar/boleto/convenios/'
                    .$convenio.'/'.$numeroBoleto
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao consultar boleto Ailos (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta Ailos de consulta de boleto inválida.');
        }

        return $json;
    }

    /**
     * Pedido de baixa (instrução) em lote — Postman: DELETE /v1/boletos/lote.
     *
     * @param  list<array{numeroConvenio: int, numeroBoleto: int|string}>  $boletos
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function baixarBoletosLote(Empresa $empresa, array $boletos): array
    {
        if ($boletos === []) {
            throw new RuntimeException('Nenhum boleto informado para baixa Ailos.');
        }

        $creds = $this->auth->credentialsForCobranca($empresa);

        $response = AilosHttp::client()
            ->withHeaders($this->headers($creds))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->send(
                'DELETE',
                $this->auth->hostForEmpresa($empresa)
                    .'/ailos/cobranca/api/v1/boletos/lote',
                ['json' => ['boletos' => array_values($boletos)]]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao solicitar baixa de boleto Ailos (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if ($json === null && $response->body() === '') {
            return ['ok' => true];
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta Ailos de baixa de boleto inválida.');
        }

        return $json;
    }

    /**
     * Pedido de alteração de vencimento em lote — Postman: PUT /v1/boletos/vencimento/lote.
     *
     * @param  list<array{numeroConvenio: int, numeroBoleto: int|string, vencimento: array{dataVencimento: string}}>  $boletos
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function alterarVencimentoLote(Empresa $empresa, array $boletos): array
    {
        if ($boletos === []) {
            throw new RuntimeException('Nenhum boleto informado para alteração de vencimento Ailos.');
        }

        $creds = $this->auth->credentialsForCobranca($empresa);

        $response = AilosHttp::client()
            ->withHeaders($this->headers($creds))
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->put(
                $this->auth->hostForEmpresa($empresa)
                    .'/ailos/cobranca/api/v1/boletos/vencimento/lote',
                ['boletos' => array_values($boletos)]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao alterar vencimento de boleto Ailos (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if ($json === null && $response->body() === '') {
            return ['ok' => true];
        }

        if (! is_array($json)) {
            throw new RuntimeException('Resposta Ailos de alteração de vencimento inválida.');
        }

        return $json;
    }

    /**
     * Consulta o andamento de uma instrução em lote (baixa, vencimento, etc.).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function consultarInstrucaoLote(Empresa $empresa, string $ticket): array
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            throw new RuntimeException('Ticket de instrução Ailos vazio.');
        }

        $creds = $this->auth->credentialsForCobranca($empresa);

        $response = AilosHttp::client()
            ->withHeaders($this->headers($creds))
            ->timeout(45)
            ->acceptJson()
            ->get(
                $this->auth->hostForEmpresa($empresa)
                    .'/ailos/cobranca/api/v1/instrucoes/lote/'.rawurlencode($ticket)
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao consultar instrução Ailos (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Resposta Ailos de consulta de instrução inválida.');
        }

        return $json;
    }

    /**
     * Número do convênio (somente dígitos) configurado na empresa.
     *
     * @throws RuntimeException
     */
    public function convenioNumero(Empresa $empresa): int
    {
        return (int) $this->convenio($empresa);
    }

    /**
     * @param  array{access_token: string, jwt: string}  $creds
     * @return array<string, string>
     */
    private function headers(array $creds): array
    {
        return [
            'Authorization' => 'Bearer '.$creds['access_token'],
            'x-ailos-authentication' => 'Bearer '.$creds['jwt'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @throws RuntimeException
     */
    private function convenio(Empresa $empresa): string
    {
        $convenio = preg_replace('/\D/', '', (string) ($empresa->param_boleto_convenio ?? '')) ?? '';
        if ($convenio === '') {
            throw new RuntimeException(
                'Número do Convênio Ailos não configurado (Empresa > Parâmetros > API Boleto).'
            );
        }

        return $convenio;
    }

    private function formatGeracaoError(int $status, string $body, string $convenio): string
    {
        $base = 'Falha ao gerar boleto Ailos (HTTP '.$status.'): '.$body;
        $lower = mb_strtolower($body);

        if (str_contains($lower, 'convênio de cobrança inválido')
            || str_contains($lower, 'convenio de cobranca invalido')
            || str_contains($lower, 'número do convênio de cobrança inválido')) {
            return $base
                .' O convênio "'.$convenio.'" não é válido para esta cooperativa/conta na Ailos. '
                .'Peça à Ailos o Número do Convênio de Cobrança correto (homologação) e atualize em '
                .'Empresa > API Boleto > Conta Ailos (campo Convênio). '
                .'O valor padrão "2" era da conta antiga (cooperativa 9) e não vale para cooperativa 11.';
        }

        return $base;
    }
}
