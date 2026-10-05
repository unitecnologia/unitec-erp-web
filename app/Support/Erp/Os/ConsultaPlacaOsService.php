<?php

namespace App\Support\Erp\Os;

use App\Models\Empresa;
use App\Models\OsVeiculo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Consulta de veículo por placa (Verifica Online), usando só a config da empresa.
 * A chave fica no header e não entra em retorno, exceção reportada nem log.
 */
final class ConsultaPlacaOsService
{
    private bool $falhaPorTimeout = false;

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    public function consultar(Empresa $empresa, string $placaInformada): array
    {
        $config = $this->config($empresa);
        if ($config['message'] !== null) {
            return $this->falha($config['message']);
        }

        $placa = $this->normalizarPlaca($placaInformada);
        if (! $this->placaValida($placa)) {
            return $this->falha('Informe uma placa válida (ABC1234 ou ABC1D23).');
        }

        $token = $config['token'];
        $timeout = $config['timeout'];
        $base = $config['base'];
        $endpoint = $base.'/datasets/consulta_placa/queries';
        $idempotency = (string) Str::uuid();
        $deadline = microtime(true) + $timeout;

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
        try {
            $response = $this->http($token, $timeout)
                ->withHeaders(['Idempotency-Key' => $idempotency])
                ->post($endpoint, ['plate' => $placa]);
        } catch (ConnectionException $exception) {
            $this->falhaPorTimeout = $this->ehTimeout($exception);

            if ($podeRepetir) {
                return $this->enviar($endpoint, $token, $placa, $idempotency, $timeout, false);
            }

            return null;
        }

        if ($podeRepetir && in_array($response->status(), [502, 503, 504], true)) {
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
            return $this->falha('A consulta de placa excedeu o tempo limite. Tente novamente.');
        }

        $url = $base.'/queries/'.$requestId;

        while (microtime(true) < $deadline) {
            usleep(400_000);

            $restante = max(1, (int) ceil($deadline - microtime(true)));

            try {
                $response = $this->http($token, $restante)->get($url);
            } catch (ConnectionException) {
                continue;
            }

            $json = $response->json();
            $json = is_array($json) ? $json : [];
            $estado = (string) ($json['status'] ?? '');

            if ($response->status() === 202 || $estado === 'processing') {
                continue;
            }

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
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(min(10, max(1, $timeout)))
            ->timeout(max(1, $timeout));
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
