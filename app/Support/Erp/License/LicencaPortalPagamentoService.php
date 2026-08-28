<?php

namespace App\Support\Erp\License;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LicencaPortalPagamentoService
{
    /**
     * Próxima mensalidade em aberto (vencimento de pagamento).
     *
     * @return array{
     *     ok: bool,
     *     message?: string,
     *     invoice_id?: int,
     *     amount?: string,
     *     description?: string,
     *     due_date?: string
     * }
     */
    public function proximaMensalidade(string $cnpj): array
    {
        if (! filled(trim((string) config('unitec.licenca_api.base_url', '')))) {
            return ['ok' => false, 'message' => 'URL do portal de licença não configurada.'];
        }

        try {
            $session = $this->loginCliente($cnpj, timeout: 4);
            $invoice = $this->proximaFaturaPendente($session, timeout: 4);

            if ($invoice === null) {
                return ['ok' => false, 'message' => 'Nenhuma mensalidade pendente no portal.'];
            }

            return [
                'ok' => true,
                'invoice_id' => (int) ($invoice['id'] ?? 0),
                'amount' => (string) ($invoice['amount'] ?? ''),
                'description' => (string) ($invoice['description'] ?? ''),
                'due_date' => (string) ($invoice['dueDate'] ?? ''),
            ];
        } catch (Throwable $e) {
            Log::warning('Falha ao consultar mensalidade no portal de licença.', [
                'cnpj' => preg_replace('/\D/', '', $cnpj),
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Carrega a próxima fatura pendente e o QR Pix do portal Unitec.
     *
     * @return array{
     *     ok: bool,
     *     message?: string,
     *     invoice_id?: int,
     *     amount?: string,
     *     description?: string,
     *     due_date?: string,
     *     br_code?: string,
     *     qr_code_data_url?: string,
     *     expiration_date?: string,
     *     ticket_url?: string
     * }
     */
    public function carregarPixPendente(string $cnpj): array
    {
        if (! filled(trim((string) config('unitec.licenca_api.base_url', '')))) {
            return ['ok' => false, 'message' => 'URL do portal de licença não configurada.'];
        }

        try {
            $session = $this->loginCliente($cnpj);
            $invoice = $this->proximaFaturaPendente($session);

            if ($invoice === null) {
                return ['ok' => false, 'message' => 'Nenhuma fatura pendente encontrada no portal.'];
            }

            $invoiceId = (int) ($invoice['id'] ?? 0);
            $pix = $this->gerarPix($session, $invoiceId);

            if ($pix === null) {
                return ['ok' => false, 'message' => 'Não foi possível gerar o QR Code Pix no portal.'];
            }

            return [
                'ok' => true,
                'invoice_id' => $invoiceId,
                'amount' => (string) ($pix['amount'] ?? $invoice['amount'] ?? ''),
                'description' => (string) ($pix['description'] ?? $invoice['description'] ?? ''),
                'due_date' => (string) ($invoice['dueDate'] ?? ''),
                'br_code' => (string) ($pix['brCode'] ?? ''),
                'qr_code_data_url' => (string) ($pix['qrCodeDataUrl'] ?? ''),
                'expiration_date' => (string) ($pix['expirationDate'] ?? ''),
                'ticket_url' => (string) ($pix['ticketUrl'] ?? ''),
            ];
        } catch (Throwable $e) {
            Log::warning('Falha ao carregar Pix do portal de licença.', [
                'cnpj' => preg_replace('/\D/', '', $cnpj),
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Falha ao consultar pagamento no portal: '.$e->getMessage()];
        }
    }

    /**
     * Confirma pagamento da fatura no portal (Mercado Pago).
     *
     * @return array{ok: bool, paid: bool, message?: string}
     */
    public function verificarPagamentoFatura(string $cnpj, int $invoiceId): array
    {
        try {
            $session = $this->loginCliente($cnpj);
            $timeout = max(3, (int) config('unitec.licenca_api.timeout', 8));
            $baseUrl = rtrim((string) config('unitec.licenca_api.base_url'), '/');

            $response = Http::withOptions(['cookies' => $session])
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/api/invoices/'.$invoiceId.'/check-payment');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'paid' => false,
                    'message' => 'Não foi possível confirmar o pagamento agora.',
                ];
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->json() ?? [];
            $paid = (bool) ($payload['updated'] ?? false)
                || strtolower((string) ($payload['status'] ?? '')) === 'paid';

            return [
                'ok' => true,
                'paid' => $paid,
                'message' => (string) ($payload['message'] ?? ($paid ? 'Pagamento confirmado.' : 'Pagamento ainda não confirmado.')),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'paid' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function formatCnpjMask(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            return $cnpj;
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }

    /**
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    private function loginCliente(string $cnpj, ?int $timeout = null): \GuzzleHttp\Cookie\CookieJar
    {
        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url'), '/');
        $timeout = max(2, $timeout ?? (int) config('unitec.licenca_api.timeout', 8));
        $jar = new \GuzzleHttp\Cookie\CookieJar;

        $response = Http::withOptions(['cookies' => $jar])
            ->timeout($timeout)
            ->connectTimeout(min(2, $timeout))
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/api/auth/login', [
                'login' => $this->formatCnpjMask($cnpj),
                'password' => '',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível autenticar o CNPJ no portal.');
        }

        return $jar;
    }

    /**
     * @param  \GuzzleHttp\Cookie\CookieJar  $session
     * @return array<string, mixed>|null
     */
    private function proximaFaturaPendente($session, ?int $timeout = null): ?array
    {
        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url'), '/');
        $timeout = max(2, $timeout ?? (int) config('unitec.licenca_api.timeout', 8));

        $response = Http::withOptions(['cookies' => $session])
            ->timeout($timeout)
            ->connectTimeout(min(2, $timeout))
            ->acceptJson()
            ->get($baseUrl.'/api/invoices');

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível listar faturas no portal.');
        }

        $items = $response->json();

        if (! is_array($items)) {
            return null;
        }

        $pending = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $status = strtolower((string) ($item['status'] ?? ''));
            $paidAt = $item['paidAt'] ?? null;

            if ($status === 'paid' || filled($paidAt)) {
                continue;
            }

            $pending[] = $item;
        }

        usort($pending, static function (array $a, array $b): int {
            return strcmp((string) ($a['dueDate'] ?? ''), (string) ($b['dueDate'] ?? ''));
        });

        return $pending[0] ?? null;
    }

    /**
     * @param  \GuzzleHttp\Cookie\CookieJar  $session
     * @return array<string, mixed>|null
     */
    private function gerarPix($session, int $invoiceId): ?array
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url'), '/');
        $timeout = max(5, (int) config('unitec.licenca_api.timeout', 8));

        $response = Http::withOptions(['cookies' => $session])
            ->timeout($timeout)
            ->acceptJson()
            ->get($baseUrl.'/api/invoices/'.$invoiceId.'/pix');

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }
}
