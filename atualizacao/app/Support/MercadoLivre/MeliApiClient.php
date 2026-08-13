<?php

namespace App\Support\MercadoLivre;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

final class MeliApiClient
{
    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function getShipment(string $accessToken, string|int $shipmentId): array
    {
        return $this->get('/shipments/'.urlencode((string) $shipmentId), $accessToken);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function getMe(string $accessToken): array
    {
        return $this->get('/users/me', $accessToken);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function getOrder(string $accessToken, string|int $orderId): array
    {
        return $this->get('/orders/'.urlencode((string) $orderId), $accessToken);
    }

    public function accessTokenForEmpresa(Empresa $empresa): ?string
    {
        $token = trim((string) $empresa->param_meli_access_token);

        if ($token === '') {
            return null;
        }

        $expiresAt = $empresa->param_meli_token_expires_at;

        if ($expiresAt && $expiresAt->isPast()) {
            $refresh = app(MeliOAuthService::class)->refreshAccessToken($empresa);

            if (! $refresh['ok'] || ! is_array($refresh['data'])) {
                return null;
            }

            $empresa->update([
                'param_meli_access_token' => $refresh['data']['access_token'] ?? $token,
                'param_meli_refresh_token' => $refresh['data']['refresh_token'] ?? $empresa->param_meli_refresh_token,
                'param_meli_token_expires_at' => $refresh['data']['expires_at'] ?? null,
            ]);

            $token = trim((string) ($refresh['data']['access_token'] ?? ''));
        }

        return $token !== '' ? $token : null;
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    private function get(string $path, string $accessToken): array
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->withToken($accessToken)
            ->get(rtrim((string) config('meli.api_url'), '/').$path);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->extractErrorMessage($response->json(), 'Falha na API do Mercado Livre.'),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [
                'ok' => false,
                'message' => 'Resposta inválida da API do Mercado Livre.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function extractErrorMessage(?array $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        $message = trim((string) ($payload['message'] ?? $payload['error_description'] ?? $payload['error'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }
}
