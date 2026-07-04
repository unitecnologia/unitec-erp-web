<?php

namespace App\Support\Pix\Providers;

use App\Models\PixCobranca;
use App\Support\Pix\Contracts\PixProvider;
use App\Support\Pix\Data\PixCobrancaInput;
use App\Support\Pix\Data\PixCobrancaResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provedor Pix via API de Pagamentos do Mercado Pago (POST /v1/payments com
 * payment_method_id = pix). Não usa a API padrão do BACEN (mTLS); usa o
 * access token da conta Mercado Pago.
 */
class MercadoPagoPixProvider implements PixProvider
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://api.mercadopago.com',
    ) {
    }

    public function nome(): string
    {
        return 'mercadopago';
    }

    public function criarCobranca(PixCobrancaInput $input): PixCobrancaResult
    {
        $payload = [
            'transaction_amount' => round($input->valor, 2),
            'description' => $input->descricao,
            'payment_method_id' => 'pix',
            'date_of_expiration' => $input->expiraEm->format('Y-m-d\TH:i:s.vP'),
            'payer' => [
                'email' => $input->payerEmail ?: 'comprador@unitecnologiasc.com.br',
            ],
        ];

        if ($input->externalReference !== null) {
            $payload['external_reference'] = $input->externalReference;
        }

        if ($input->notificationUrl !== null) {
            $payload['notification_url'] = $input->notificationUrl;
        }

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['X-Idempotency-Key' => $input->txid])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->baseUrl.'/v1/payments', $payload);

        if (! $response->successful()) {
            $msg = $response->json('message') ?? $response->body();

            throw new RuntimeException('Mercado Pago recusou a cobrança Pix: '.$msg);
        }

        $data = $response->json();
        $tx = $data['point_of_interaction']['transaction_data'] ?? [];
        $copiaCola = $tx['qr_code'] ?? '';

        if ($copiaCola === '') {
            throw new RuntimeException('Mercado Pago não retornou o QR Code Pix.');
        }

        return new PixCobrancaResult(
            providerRef: (string) ($data['id'] ?? ''),
            qrCopiaCola: $copiaCola,
            qrImagemBase64: $tx['qr_code_base64'] ?? null,
            status: $this->mapStatus((string) ($data['status'] ?? 'pending')),
            raw: is_array($data) ? $data : [],
        );
    }

    public function consultarStatus(string $providerRef): string
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl.'/v1/payments/'.$providerRef);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar o pagamento no Mercado Pago.');
        }

        return $this->mapStatus((string) ($response->json('status') ?? 'pending'));
    }

    public function parseWebhook(Request $request): ?string
    {
        // O Mercado Pago manda o id ora na query (topic/type + id / data.id),
        // ora no corpo JSON ({ type: 'payment', data: { id } }).
        $type = (string) ($request->input('type', $request->input('topic', '')));

        if ($type !== '' && $type !== 'payment') {
            return null;
        }

        $id = $request->input('data.id')
            ?? $request->input('id')
            ?? $request->query('id')
            ?? $request->input('data_id');

        $id = is_scalar($id) ? (string) $id : '';

        return $id !== '' ? $id : null;
    }

    /**
     * Normaliza o status do Mercado Pago para os status do PixCobranca.
     */
    private function mapStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => PixCobranca::STATUS_PAGO,
            'cancelled', 'rejected', 'refunded', 'charged_back' => PixCobranca::STATUS_CANCELADO,
            default => PixCobranca::STATUS_PENDENTE, // pending, in_process, authorized, in_mediation
        };
    }
}
