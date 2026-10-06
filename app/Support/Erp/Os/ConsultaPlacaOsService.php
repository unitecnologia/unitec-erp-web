<?php

namespace App\Support\Erp\Os;

use App\Models\Empresa;
use App\Models\OsVeiculo;
use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Consulta de veículo por placa (Verifica Online), usando só a config da empresa.
 * A chave fica no header e não entra em retorno nem exceção reportada; no log
 * (canal consulta_placa) aparece apenas mascarada.
 */
final class ConsultaPlacaOsService
{
    private const LOG_CHANNEL = 'consulta_placa';

    private const LOG_CORPO_MAX = 2000;

    private bool $falhaPorTimeout = false;

    /** @var array<string, mixed> */
    private array $contextoLog = [];

    private string $tokenAtual = '';

    private int $tentativas = 0;

    private int $respostasProcessando = 0;

    private ?string $tipoFalha = null;

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    public function consultar(Empresa $empresa, string $placaInformada): array
    {
        $this->falhaPorTimeout = false;
        $this->tokenAtual = '';
        $this->tentativas = 0;
        $this->respostasProcessando = 0;
        $this->tipoFalha = null;
        $this->contextoLog = [
            'consulta_id' => (string) Str::uuid(),
            'empresa' => $this->empresaLog($empresa),
            'placa' => $this->normalizarPlaca($placaInformada),
        ];

        $inicio = microtime(true);

        try {
            $resultado = $this->executarConsulta($empresa, $placaInformada);
        } catch (Throwable $exception) {
            $this->registrar('error', 'Consulta de placa interrompida por exceção.', [
                'resultado' => 'falha',
                'tipo_falha' => 'excecao',
                'tentativas_http' => $this->tentativas,
                'duracao_total_ms' => $this->duracaoMs($inicio),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $ok = (bool) ($resultado['ok'] ?? false);
        $this->registrar($ok ? 'info' : 'warning', 'Consulta de placa finalizada.', array_filter([
            'resultado' => $ok ? 'sucesso' : 'falha',
            'tipo_falha' => $ok ? null : ($this->tipoFalha ?? 'desconhecida'),
            'mensagem_usuario' => (string) ($resultado['message'] ?? ''),
            'tentativas_http' => $this->tentativas,
            'respostas_processando' => $this->respostasProcessando ?: null,
            'duracao_total_ms' => $this->duracaoMs($inicio),
        ], static fn (mixed $valor): bool => $valor !== null));

        return $resultado;
    }

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    private function executarConsulta(Empresa $empresa, string $placaInformada): array
    {
        $config = $this->config($empresa);
        if ($config['message'] !== null) {
            $this->tipoFalha = 'configuracao';

            return $this->falha($config['message']);
        }

        $placa = $this->normalizarPlaca($placaInformada);
        if (! $this->placaValida($placa)) {
            $this->tipoFalha = 'placa_invalida';

            return $this->falha('Informe uma placa válida (ABC1234 ou ABC1D23).');
        }

        $token = $config['token'];
        $timeout = $config['timeout'];
        $base = $config['base'];
        $endpoint = $base.'/datasets/consulta_placa/queries';
        $idempotency = (string) $this->contextoLog['consulta_id'];
        $deadline = microtime(true) + $timeout;

        $this->tokenAtual = $token;
        $this->contextoLog['host'] = (string) parse_url($base, PHP_URL_HOST);
        $this->contextoLog['token'] = $this->mascararToken($token);
        $this->contextoLog['timeout_s'] = $timeout;

        $response = $this->enviar($endpoint, $token, $placa, $idempotency, $timeout, true);
        if ($response === null) {
            return $this->falha($this->falhaPorTimeout
                ? 'A consulta de placa excedeu o tempo limite. Tente novamente.'
                : 'A consulta de placa está indisponível no momento. Tente novamente.');
        }

        $resultado = $this->interpretar($response, $base, $token, $deadline);
        $vehicle = $resultado['vehicle'] ?? null;
        unset($resultado['vehicle']);

        if (is_array($vehicle)) {
            $salvo = OsVeiculo::salvarDaApi((int) $empresa->id, $placa, $vehicle);
            $resultado['extras'] = $salvo->extrasEquipamento();
            if (($resultado['fields']['placa'] ?? '') === '') {
                $resultado['fields']['placa'] = $placa;
            }
        }

        return $resultado;
    }

    /**
     * @return array{message: ?string, token: string, timeout: int, base: string}
     */
    private function config(Empresa $empresa): array
    {
        $vazio = ['message' => null, 'token' => '', 'timeout' => 0, 'base' => ''];

        if (! filter_var(config('unitec.consulta_placa.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $vazio['message'] = 'A consulta por placa não está habilitada.';

            return $vazio;
        }

        $base = rtrim(trim((string) config('unitec.consulta_placa.base_url', '')), '/');
        $sufixo = '/datasets/consulta_placa/queries';
        if (str_ends_with($base, $sufixo)) {
            $base = substr($base, 0, -strlen($sufixo));
        }

        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false || ! preg_match('#^https?://#i', $base)) {
            $vazio['message'] = 'Informe a URL da API de consulta por placa nos parâmetros da empresa.';

            return $vazio;
        }

        $token = trim((string) config('unitec.consulta_placa.token', ''));

        if ($token === '') {
            $vazio['message'] = 'Informe a API Key da consulta por placa nos parâmetros da empresa.';

            return $vazio;
        }

        $timeout = (int) config('unitec.consulta_placa.timeout', 0);
        if ($timeout < 1 || $timeout > 300) {
            $vazio['message'] = 'Informe o timeout da consulta por placa nos parâmetros da empresa.';

            return $vazio;
        }

        return [
            'message' => null,
            'token' => $token,
            'timeout' => $timeout,
            'base' => $base,
        ];
    }

    private function enviar(
        string $endpoint,
        string $token,
        string $placa,
        string $idempotency,
        int $timeout,
        bool $podeRepetir,
    ): ?Response {
        $this->tentativas++;
        $inicio = microtime(true);
        $iniciadoEm = now()->format('Y-m-d H:i:s.v');

        try {
            $response = $this->http($token, $timeout)
                ->withHeaders(['Idempotency-Key' => $idempotency])
                ->post($endpoint, ['plate' => $placa]);
        } catch (ConnectionException $exception) {
            $this->falhaPorTimeout = $this->ehTimeout($exception);

            $this->registrarFalhaConexao('POST', $endpoint, $inicio, $iniciadoEm, $exception, $podeRepetir);

            if ($podeRepetir) {
                return $this->enviar($endpoint, $token, $placa, $idempotency, $timeout, false);
            }

            return null;
        }

        $repetir = $podeRepetir && in_array($response->status(), [502, 503, 504], true);
        $this->registrarResposta('POST', $endpoint, $inicio, $iniciadoEm, $response, $repetir);

        if ($repetir) {
            return $this->enviar($endpoint, $token, $placa, $idempotency, $timeout, false);
        }

        return $response;
    }

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    private function interpretar(Response $response, string $base, string $token, float $deadline): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : [];
        $codigo = (string) data_get($json, 'error.code', '');
        $status = $response->status();

        if ($status === 402 || $codigo === 'insufficient_balance') {
            return $this->falha('Saldo insuficiente para consultar a placa.');
        }

        if ($status === 401) {
            return $this->falha('A API Key da consulta por placa foi recusada. Confira a chave nos parâmetros da empresa.');
        }

        if ($status === 422) {
            return $this->falha('Informe uma placa válida (ABC1234 ou ABC1D23).');
        }

        if ($status === 504) {
            return $this->falha('A consulta de placa excedeu o tempo limite. Tente novamente.');
        }

        if (in_array($status, [502, 503], true) || in_array($codigo, ['dataset_unavailable', 'result_unavailable'], true)) {
            return $this->falha('A consulta de placa está indisponível no momento. Tente novamente.');
        }

        $estado = (string) ($json['status'] ?? '');
        if ($status === 202 || $estado === 'processing') {
            return $this->aguardar($base, $token, (string) ($json['request_id'] ?? ''), $deadline);
        }

        if ($status >= 400) {
            return $this->falha('Não foi possível consultar a placa. Tente novamente.');
        }

        return $this->mapearEnvelope($json);
    }

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    private function aguardar(string $base, string $token, string $requestId, float $deadline): array
    {
        if (! Str::isUuid($requestId)) {
            $this->tipoFalha = 'resposta_invalida';
            $this->registrar('warning', 'Consulta de placa: API retornou processamento sem request_id válido.', [
                'request_id' => $this->limitarTexto($requestId),
            ]);

            return $this->falha('A consulta de placa excedeu o tempo limite. Tente novamente.');
        }

        $url = $base.'/queries/'.$requestId;

        while (microtime(true) < $deadline) {
            usleep(400_000);

            $restante = max(1, (int) ceil($deadline - microtime(true)));

            $this->tentativas++;
            $inicio = microtime(true);
            $iniciadoEm = now()->format('Y-m-d H:i:s.v');

            try {
                $response = $this->http($token, $restante)->get($url);
            } catch (ConnectionException $exception) {
                $this->registrarFalhaConexao('GET', $url, $inicio, $iniciadoEm, $exception, true);

                continue;
            }

            $json = $response->json();
            $json = is_array($json) ? $json : [];
            $estado = (string) ($json['status'] ?? '');

            if ($response->status() === 202 || $estado === 'processing') {
                $this->respostasProcessando++;

                continue;
            }

            $this->registrarResposta('GET', $url, $inicio, $iniciadoEm, $response, in_array($response->status(), [502, 503], true));

            if ($response->status() === 504) {
                return $this->falha('A consulta de placa excedeu o tempo limite. Tente novamente.');
            }

            if (in_array($response->status(), [502, 503], true)) {
                continue;
            }

            if ($response->status() >= 400) {
                return $this->falha('Não foi possível consultar a placa. Tente novamente.');
            }

            return $this->mapearEnvelope($json);
        }

        $this->tipoFalha = 'timeout';
        $this->registrar('warning', 'Consulta de placa: tempo limite esgotado aguardando o processamento da API.', [
            'request_id' => $requestId,
        ]);

        return $this->falha('A consulta de placa excedeu o tempo limite. Tente novamente.');
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{ok: bool, message: string, fields: array<string, string>, vehicle?: array<string, mixed>}
     */
    private function mapearEnvelope(array $json): array
    {
        $estado = (string) ($json['status'] ?? '');

        if ($estado === 'no_data') {
            return $this->falha('Placa não encontrada.');
        }

        if ($estado === 'failed') {
            return $this->falha('Não foi possível consultar a placa. Tente novamente.');
        }

        if ($estado !== 'succeeded') {
            return $this->falha('Não foi possível consultar a placa. Tente novamente.');
        }

        $vehicle = data_get($json, 'data.vehicle');
        if (! is_array($vehicle)) {
            return $this->falha('Placa não encontrada.');
        }

        return [
            'ok' => true,
            'message' => 'Dados do veículo preenchidos.',
            'fields' => $this->camposOs($vehicle),
            'vehicle' => $vehicle,
        ];
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return array<string, string>
     */
    private function camposOs(array $vehicle): array
    {
        $fields = [];

        $placa = $this->normalizarPlaca((string) ($vehicle['plate'] ?? ''));
        if ($this->placaValida($placa)) {
            $fields['placa'] = $placa;
        }

        $marca = $this->texto($vehicle['brand'] ?? null);
        $modelo = $this->texto($vehicle['model'] ?? null);
        $versao = $this->texto($vehicle['version'] ?? null);
        $descricao = trim(implode(' ', array_filter([$marca, $modelo, $versao], static fn (string $parte): bool => $parte !== '')));
        if ($descricao !== '') {
            $fields['descricao'] = $this->maiusculo($descricao);
        }

        if ($modelo !== '') {
            $fields['modelo'] = $this->maiusculo($modelo);
        }

        $fabricacao = $this->anoParte($vehicle['manufacture_year'] ?? null);
        $anoModelo = $this->anoParte($vehicle['model_year'] ?? null);
        if ($fabricacao !== '' && $anoModelo !== '') {
            $fields['ano'] = $fabricacao.'/'.$anoModelo;
        } elseif ($fabricacao !== '') {
            $fields['ano'] = $fabricacao;
        } elseif ($anoModelo !== '') {
            $fields['ano'] = $anoModelo;
        }

        $cor = $this->texto($vehicle['color'] ?? null);
        if ($cor !== '') {
            $fields['cor'] = $this->maiusculo($cor);
        }

        $chassi = $this->texto($vehicle['chassis'] ?? null);
        if ($chassi !== '') {
            $fields['chassi'] = $this->maiusculo($chassi);
        }

        return $fields;
    }

    private function http(string $token, int $timeout): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->withOptions(LicencaHttpClient::options(['allow_redirects' => false]))
            ->connectTimeout(min(10, max(1, $timeout)))
            ->timeout(max(1, $timeout));
    }

    private function registrarFalhaConexao(
        string $metodo,
        string $url,
        float $inicio,
        string $iniciadoEm,
        ConnectionException $exception,
        bool $seraRepetida,
    ): void {
        $curl = $this->contextoCurl($exception);
        $this->tipoFalha = $this->tipoFalhaConexao($curl['errno'], $exception->getMessage());

        $this->registrar('error', 'Consulta de placa: falha de comunicação com a API.', [
            'tentativa' => $this->tentativas,
            'metodo' => $metodo,
            'url' => $url,
            'inicio' => $iniciadoEm,
            'duracao_ms' => $this->duracaoMs($inicio),
            'http_status' => null,
        ] + array_filter([
            'resultado' => 'falha',
            'tipo_falha' => $this->tipoFalha,
            'curl_errno' => $curl['errno'],
            'curl_error' => $curl['error'] !== null ? $this->limparTexto($curl['error']) : null,
            'conexao' => $curl['stats'] ?: null,
            'nova_tentativa' => $seraRepetida,
            'exception' => $exception,
        ], static fn (mixed $valor): bool => $valor !== null));
    }

    private function registrarResposta(
        string $metodo,
        string $url,
        float $inicio,
        string $iniciadoEm,
        Response $response,
        bool $seraRepetida,
    ): void {
        $status = $response->status();
        $json = $response->json();
        $codigo = is_array($json) ? (string) data_get($json, 'error.code', '') : '';
        $mensagemApi = is_array($json) ? $this->mensagemApi($json) : '';

        $base = [
            'tentativa' => $this->tentativas,
            'metodo' => $metodo,
            'url' => $url,
            'inicio' => $iniciadoEm,
            'duracao_ms' => $this->duracaoMs($inicio),
            'http_status' => $status,
        ];

        $detalhesFalha = fn (): array => array_filter([
            'codigo_api' => $codigo,
            'mensagem_api' => $mensagemApi,
            'content_type' => (string) $response->header('Content-Type'),
            'corpo' => $this->limitarTexto(trim($response->body())),
            'conexao' => $this->estatisticasConexao($response->handlerStats()) ?: null,
            'nova_tentativa' => $seraRepetida,
        ], static fn (mixed $valor): bool => $valor !== '' && $valor !== null);

        if ($status >= 400) {
            $this->tipoFalha = ($status === 402 || $codigo === 'insufficient_balance') ? 'saldo_insuficiente' : 'http_'.$status;
            $this->registrar('warning', 'Consulta de placa: API respondeu com erro HTTP.', $base + [
                'resultado' => 'falha',
                'tipo_falha' => $this->tipoFalha,
            ] + $detalhesFalha());

            return;
        }

        if (! is_array($json)) {
            $this->tipoFalha = 'resposta_invalida';
            $this->registrar('warning', 'Consulta de placa: resposta da API não é um JSON válido.', $base + [
                'resultado' => 'falha',
                'tipo_falha' => $this->tipoFalha,
            ] + $detalhesFalha());

            return;
        }

        $estado = (string) ($json['status'] ?? '');
        $requestId = (string) ($json['request_id'] ?? '');

        if ($status === 202 || $estado === 'processing') {
            $this->registrar('info', 'Consulta de placa: API aceitou a consulta e está processando.', $base + array_filter([
                'resultado' => 'processando',
                'request_id' => $requestId,
            ]));

            return;
        }

        $vehicle = data_get($json, 'data.vehicle');

        if ($estado === 'succeeded' && is_array($vehicle)) {
            $this->tipoFalha = null;
            $this->registrar('info', 'Consulta de placa: veículo retornado pela API.', $base + [
                'resultado' => 'sucesso',
                'resposta' => array_filter([
                    'request_id' => $requestId,
                    'status' => $estado,
                    'placa' => $this->texto($vehicle['plate'] ?? null),
                    'marca' => $this->texto($vehicle['brand'] ?? null),
                    'modelo' => $this->texto($vehicle['model'] ?? null),
                    'ano' => trim($this->anoParte($vehicle['manufacture_year'] ?? null).'/'.$this->anoParte($vehicle['model_year'] ?? null), '/'),
                ]),
            ]);

            return;
        }

        if ($estado === 'no_data') {
            $this->tipoFalha = 'placa_nao_encontrada';
            $this->registrar('info', 'Consulta de placa: placa não encontrada na base da API.', $base + array_filter([
                'resultado' => 'sem_dados',
                'request_id' => $requestId,
                'mensagem_api' => $mensagemApi,
            ]));

            return;
        }

        $this->tipoFalha = $estado === 'failed' ? 'api_falhou' : 'resposta_invalida';
        $this->registrar('warning', $estado === 'failed'
            ? 'Consulta de placa: API informou falha no processamento.'
            : 'Consulta de placa: resposta da API em formato inesperado.', $base + array_filter([
                'resultado' => 'falha',
                'tipo_falha' => $this->tipoFalha,
                'status_api' => $estado,
                'request_id' => $requestId,
            ]) + $detalhesFalha());
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function registrar(string $nivel, string $mensagem, array $contexto = []): void
    {
        try {
            Log::channel(self::LOG_CHANNEL)->log($nivel, $mensagem, array_merge($this->contextoLog, $contexto));
        } catch (Throwable) {
            // Falha ao gravar o log não pode interromper a consulta.
        }
    }

    /**
     * @return array{errno: ?int, error: ?string, stats: array<string, mixed>}
     */
    private function contextoCurl(Throwable $exception): array
    {
        $handler = [];
        for ($atual = $exception; $atual !== null; $atual = $atual->getPrevious()) {
            if (method_exists($atual, 'getHandlerContext')) {
                $handler = (array) $atual->getHandlerContext();
                break;
            }
        }

        $errno = isset($handler['errno']) ? (int) $handler['errno'] : null;
        if ($errno === null && preg_match('/cURL error (\d+)/', $exception->getMessage(), $match) === 1) {
            $errno = (int) $match[1];
        }

        $error = isset($handler['error']) && $handler['error'] !== '' ? (string) $handler['error'] : null;

        return [
            'errno' => $errno,
            'error' => $error,
            'stats' => $this->estatisticasConexao($handler),
        ];
    }

    private function tipoFalhaConexao(?int $errno, string $mensagem): string
    {
        $tipo = match ($errno) {
            5, 6 => 'dns',
            28 => 'timeout',
            35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91 => 'ssl',
            7, 52, 55, 56 => 'conexao',
            default => null,
        };

        if ($tipo !== null) {
            return $tipo;
        }

        $mensagem = strtolower($mensagem);

        return match (true) {
            str_contains($mensagem, 'resolve host'), str_contains($mensagem, 'name or service not known') => 'dns',
            str_contains($mensagem, 'timed out'), str_contains($mensagem, 'timeout') => 'timeout',
            str_contains($mensagem, 'ssl'), str_contains($mensagem, 'certificate'), str_contains($mensagem, 'tls') => 'ssl',
            default => 'conexao',
        };
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function estatisticasConexao(array $stats): array
    {
        $campos = ['primary_ip', 'primary_port', 'namelookup_time', 'connect_time', 'appconnect_time', 'total_time', 'ssl_verify_result'];

        return array_filter(
            array_intersect_key($stats, array_flip($campos)),
            static fn (mixed $valor): bool => $valor !== null && $valor !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function mensagemApi(array $json): string
    {
        foreach (['error.message', 'message', 'detail', 'error'] as $chave) {
            $valor = data_get($json, $chave);
            if (is_string($valor) && trim($valor) !== '') {
                return $this->limitarTexto(trim($valor));
            }
        }

        return '';
    }

    private function empresaLog(Empresa $empresa): string
    {
        $nome = trim((string) ($empresa->fantasia ?: $empresa->razao_social ?: $empresa->nome));

        return trim($empresa->id.' - '.$nome, ' -');
    }

    private function mascararToken(string $token): string
    {
        $tamanho = strlen($token);
        if ($tamanho < 16) {
            return str_repeat('*', 4).substr($token, -2);
        }

        return substr($token, 0, 4).'****'.substr($token, -4);
    }

    private function limparTexto(string $texto): string
    {
        if ($this->tokenAtual === '') {
            return $texto;
        }

        return str_replace($this->tokenAtual, $this->mascararToken($this->tokenAtual), $texto);
    }

    private function limitarTexto(string $texto): string
    {
        return Str::limit($this->limparTexto($texto), self::LOG_CORPO_MAX);
    }

    private function duracaoMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }

    private function ehTimeout(ConnectionException $exception): bool
    {
        $mensagem = $exception->getMessage();

        return str_contains($mensagem, 'cURL error 28')
            || str_contains(strtolower($mensagem), 'timed out');
    }

    private function normalizarPlaca(string $placa): string
    {
        $placa = str_replace([' ', '-'], '', $placa);

        return mb_strtoupper($placa, 'UTF-8');
    }

    private function placaValida(string $placa): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)
            || (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa);
    }

    private function texto(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private function anoParte(mixed $valor): string
    {
        $texto = $this->texto($valor);
        if ($texto === '' || $texto === '0') {
            return '';
        }

        if (preg_match('/^(\d{4})/', $texto, $match) === 1) {
            return $match[1];
        }

        return $texto;
    }

    private function maiusculo(string $valor): string
    {
        return mb_strtoupper($valor, 'UTF-8');
    }

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    private function falha(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'fields' => [],
        ];
    }
}
