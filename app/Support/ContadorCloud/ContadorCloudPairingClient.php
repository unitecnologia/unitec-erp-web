<?php

namespace App\Support\ContadorCloud;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

final class ContadorCloudPairingClient
{
    /**
     * @return array{ok: bool, message: string, data: ?array}
     */
    public function solicitarVinculo(Empresa $empresa, ?string $portalBaseUrl = null): array
    {
        $endpoint = ContadorCloudHttpHelper::pairingRequestUrl((string) ($portalBaseUrl ?? ''));

        try {
            $response = Http::timeout((int) config('contador-cloud.default_timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $this->buildEmpresaPayload($empresa));

            if ($response->status() === 404) {
                return [
                    'ok' => false,
                    'message' => 'O portal ainda não disponibilizou o endpoint de vínculo automático. '
                        .'Envie o arquivo docs/portal-contador-vinculo-api.md para o time do portal.',
                    'data' => null,
                ];
            }

            if (! ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                return [
                    'ok' => false,
                    'message' => ContadorCloudHttpHelper::invalidApiMessage($response, $endpoint),
                    'data' => null,
                ];
            }

            if (! $response->successful()) {
                $json = $response->json();
                $error = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? '') : trim($response->body());

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'Portal respondeu: '.$error
                        : 'Portal respondeu com status '.$response->status().'.',
                    'data' => null,
                ];
            }

            $json = $response->json();

            if (! is_array($json) || blank($json['vinculoId'] ?? null)) {
                return [
                    'ok' => false,
                    'message' => 'Resposta inválida do portal ao solicitar vínculo.',
                    'data' => null,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Solicitação enviada ao portal.',
                'data' => $json,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Não foi possível contactar o portal: '.$exception->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, data: ?array}
     */
    public function consultarStatus(string $vinculoId, ?string $portalBaseUrl = null): array
    {
        $endpoint = ContadorCloudHttpHelper::pairingStatusUrl($vinculoId, (string) ($portalBaseUrl ?? ''));

        try {
            $response = Http::timeout((int) config('contador-cloud.default_timeout', 30))
                ->acceptJson()
                ->get($endpoint);

            if ($response->status() === 404) {
                return [
                    'ok' => false,
                    'message' => 'Solicitação de vínculo não encontrada ou expirada.',
                    'data' => ['status' => 'expired'],
                ];
            }

            if (! ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                return [
                    'ok' => false,
                    'message' => ContadorCloudHttpHelper::invalidApiMessage($response, $endpoint),
                    'data' => null,
                ];
            }

            if (! $response->successful()) {
                $json = $response->json();
                $error = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? '') : trim($response->body());

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'Portal respondeu: '.$error
                        : 'Portal respondeu com status '.$response->status().'.',
                    'data' => is_array($json) ? $json : null,
                ];
            }

            $json = $response->json();

            return [
                'ok' => true,
                'message' => 'Status consultado.',
                'data' => is_array($json) ? $json : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Não foi possível consultar o portal: '.$exception->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmpresaPayload(Empresa $empresa): array
    {
        return [
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj),
            'razaoSocial' => mb_strtoupper(trim((string) ($empresa->razao_social ?: $empresa->nome)), 'UTF-8'),
            'nomeFantasia' => mb_strtoupper(trim((string) ($empresa->fantasia ?: $empresa->nome)), 'UTF-8'),
            'ie' => trim((string) ($empresa->ie ?? '')),
            'email' => trim((string) ($empresa->email ?? '')),
            'cidade' => trim((string) ($empresa->cidade ?? '')),
            'uf' => strtoupper(trim((string) ($empresa->uf ?? ''))),
            'erpOrigem' => 'unitec-erp-web',
            'erpEmpresaId' => (string) $empresa->getKey(),
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }
}
