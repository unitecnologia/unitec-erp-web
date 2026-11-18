<?php

namespace App\Support\Erp\Tef;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP SuperTEF (leve). POST de pagamento é idempotente só por order_id do chamador —
 * este cliente nunca reenvia sozinho.
 */
final class SuperTefApiClient
{
    public const BASE_URL = 'https://api.supertef.com.br/api';

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = self::BASE_URL,
    ) {}

    /**
     * @return array{ok: bool, http: int, message: string, total: int, items: list<array<string, mixed>>}
     */
    public function listPos(string $clienteChave): array
    {
        $token = trim($this->token);
        $clienteChave = trim($clienteChave);

        if ($token === '') {
            return $this->failList(0, 'Token da software house não configurado.');
        }

        if ($clienteChave === '') {
            return $this->failList(0, 'Chave do cliente SuperTEF não informada.');
        }

        try {
            $response = $this->http()->get('/pos', ['cliente_chave' => $clienteChave]);
        } catch (ConnectionException) {
            return $this->failList(0, 'Falha de rede com a API SuperTEF.');
        } catch (\Throwable) {
            return $this->failList(0, 'Falha ao consultar a API SuperTEF.');
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            return $this->failList($status, 'Token da software house recusado pela API.');
        }

        if (! $response->successful()) {
            $msg = trim((string) ($response->json('error') ?? $response->json('message') ?? ''));

            return $this->failList($status, $msg !== '' ? $msg : 'API SuperTEF retornou HTTP '.$status.'.');
        }

        $data = $response->json('data');
        $items = [];

        if (is_array($data)) {
            foreach ($data as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $items[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nome' => trim((string) ($row['nome'] ?? '')),
                    'banco' => trim((string) ($row['banco'] ?? '')),
                    'marca' => trim((string) ($row['marca'] ?? '')),
                    'modelo' => trim((string) ($row['modelo'] ?? '')),
                    'status' => (int) ($row['status'] ?? 0),
                    'ativada' => filled(trim((string) ($row['date_ativacao'] ?? ''))),
                ];
            }
        }

        return [
            'ok' => true,
            'http' => $status,
            'message' => 'Conexão OK.',
            'total' => (int) ($response->json('total') ?? count($items)),
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{ok: bool, message: string, pos: ?array<string, mixed>}
     */
    public function validatePos(array $items, int $posId): array
    {
        if ($posId <= 0) {
            return ['ok' => false, 'message' => 'Informe o POS_ID do terminal.', 'pos' => null];
        }

        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) !== $posId) {
                continue;
            }

            if (! ($item['ativada'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => 'POS '.$posId.' encontrada, mas ainda sem ativação.',
                    'pos' => $item,
                ];
            }

            return [
                'ok' => true,
                'message' => 'POS '.$posId.' vinculada ao cliente e ativada.',
                'pos' => $item,
            ];
        }

        return [
            'ok' => false,
            'message' => 'POS '.$posId.' não pertence a este cliente SuperTEF.',
            'pos' => null,
        ];
    }

    /**
     * @param  array{
     *   cliente_chave: string,
     *   pos_id: int,
     *   transaction_type: string|int,
     *   installment_count: int,
     *   installment_type: int,
     *   amount: float|int|string,
     *   order_id: string,
     *   description?: string,
     *   print_receipt?: bool
     * }  $payload
     * @return array{ok: bool, http: int, message: string, body: array<string, mixed>, transport_error: bool}
     */
    public function createPayment(array $payload): array
    {
        if (trim($this->token) === '') {
            return $this->failPayment(0, 'Token da software house não configurado.', false);
        }

        try {
            $response = $this->http()->post('/pagamentos', $payload);
        } catch (ConnectionException) {
            return $this->failPayment(0, 'Falha de rede ao solicitar pagamento SuperTEF.', true);
        } catch (\Throwable) {
            return $this->failPayment(0, 'Falha ao solicitar pagamento SuperTEF.', true);
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $http = $response->status();

        if (! $response->successful()) {
            $msg = trim((string) ($body['error'] ?? $body['message'] ?? ''));

            return $this->failPayment($http, $msg !== '' ? $msg : 'HTTP '.$http, false, $body);
        }

        return [
            'ok' => true,
            'http' => $http,
            'message' => trim((string) ($body['payment_message'] ?? 'OK')),
            'body' => $body,
            'transport_error' => false,
        ];
    }

    /**
     * @return array{ok: bool, http: int, message: string, body: array<string, mixed>, transport_error: bool}
     */
    public function getPaymentByUniqueId(int $paymentUniqueId): array
    {
        if ($paymentUniqueId <= 0) {
            return $this->failPayment(0, 'payment_uniqueid inválido.', false);
        }

        try {
            $response = $this->http()->get('/pagamentos/by-uniqueid/'.$paymentUniqueId);
        } catch (ConnectionException) {
            return $this->failPayment(0, 'Falha de rede ao consultar pagamento SuperTEF.', true);
        } catch (\Throwable) {
            return $this->failPayment(0, 'Falha ao consultar pagamento SuperTEF.', true);
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $http = $response->status();

        if (! $response->successful()) {
            $msg = trim((string) ($body['error'] ?? $body['message'] ?? ''));

            return $this->failPayment($http, $msg !== '' ? $msg : 'HTTP '.$http, false, $body);
        }

        return [
            'ok' => true,
            'http' => $http,
            'message' => trim((string) ($body['payment_message'] ?? 'OK')),
            'body' => $body,
            'transport_error' => false,
        ];
    }

    /**
     * Recuperação segura por order_id (sem novo POST).
     *
     * @return array{ok: bool, http: int, message: string, items: list<array<string, mixed>>, transport_error: bool}
     */
    public function findPaymentsByOrderId(string $clienteChave, string $orderId, ?string $dataInicial = null, ?string $dataFinal = null): array
    {
        $clienteChave = trim($clienteChave);
        $orderId = trim($orderId);

        if ($clienteChave === '' || $orderId === '') {
            return [
                'ok' => false,
                'http' => 0,
                'message' => 'cliente_chave e order_id são obrigatórios.',
                'items' => [],
                'transport_error' => false,
            ];
        }

        $query = [
            'cliente_chave' => $clienteChave,
            'order_id' => $orderId,
            'data_inicial' => $dataInicial ?: date('Y-m-d 00:00:00'),
            'data_final' => $dataFinal ?: date('Y-m-d 23:59:59'),
        ];

        try {
            $response = $this->http()->get('/pagamentos', $query);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'http' => 0,
                'message' => 'Falha de rede ao recuperar pagamento SuperTEF.',
                'items' => [],
                'transport_error' => true,
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'http' => 0,
                'message' => 'Falha ao recuperar pagamento SuperTEF.',
                'items' => [],
                'transport_error' => true,
            ];
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $http = $response->status();

        if (! $response->successful()) {
            $msg = trim((string) ($body['error'] ?? $body['message'] ?? ''));

            return [
                'ok' => false,
                'http' => $http,
                'message' => $msg !== '' ? $msg : 'HTTP '.$http,
                'items' => [],
                'transport_error' => false,
            ];
        }

        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }

        return [
            'ok' => true,
            'http' => $http,
            'message' => 'OK',
            'items' => $items,
            'transport_error' => false,
        ];
    }

    /**
     * @return array{ok: bool, http: int, message: string, body: array<string, mixed>, transport_error: bool}
     */
    public function cancelPayment(int $paymentUniqueId): array
    {
        if ($paymentUniqueId <= 0) {
            return $this->failPayment(0, 'payment_uniqueid inválido.', false);
        }

        try {
            $response = $this->http()->put('/pagamentos/cancelar/'.$paymentUniqueId);
        } catch (ConnectionException) {
            return $this->failPayment(0, 'Falha de rede ao cancelar pagamento SuperTEF.', true);
        } catch (\Throwable) {
            return $this->failPayment(0, 'Falha ao cancelar pagamento SuperTEF.', true);
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $http = $response->status();
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;

        if (! $response->successful() || (($body['status'] ?? true) === false)) {
            $msg = trim((string) ($body['error'] ?? $body['message'] ?? ''));

            return $this->failPayment($http, $msg !== '' ? $msg : 'HTTP '.$http, false, is_array($data) ? $data : $body);
        }

        return [
            'ok' => true,
            'http' => $http,
            'message' => trim((string) ($body['message'] ?? 'Cancelado')),
            'body' => is_array($data) ? $data : $body,
            'transport_error' => false,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->withToken(trim($this->token))
            ->timeout(15)
            ->connectTimeout(8);
    }

    /**
     * @return array{ok: bool, http: int, message: string, total: int, items: list<array<string, mixed>>}
     */
    private function failList(int $http, string $message): array
    {
        return [
            'ok' => false,
            'http' => $http,
            'message' => $message,
            'total' => 0,
            'items' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, http: int, message: string, body: array<string, mixed>, transport_error: bool}
     */
    private function failPayment(int $http, string $message, bool $transportError, array $body = []): array
    {
        return [
            'ok' => false,
            'http' => $http,
            'message' => $message,
            'body' => $body,
            'transport_error' => $transportError,
        ];
    }
}
