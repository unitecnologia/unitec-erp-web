<?php

namespace App\Support\ContadorCloud;

use App\Models\Contador;
use App\Models\Empresa;
use App\Support\Erp\License\LicencaHttpClient;

final class ContadorCloudPairingClient
{
    /**
     * Vínculo automático: empresa + CNPJ do contador → credenciais na hora.
     *
     * @return array{ok: bool, message: string, data: ?array, code: ?string}
     */
    public function vincularAutomatico(
        Empresa $empresa,
        Contador $contador,
        ?string $portalBaseUrl = null,
    ): array {
        $secret = trim((string) config('contador-cloud.auto_vinculo_secret', ''));

        if ($secret === '') {
            return [
                'ok' => false,
                'message' => 'Segredo ERP_AUTO_VINCULO_SECRET não configurado no ERP. Configure o mesmo valor do portal.',
                'data' => null,
                'code' => 'auto_secret_ausente',
            ];
        }

        $endpoint = ContadorCloudHttpHelper::pairingAutoUrl((string) ($portalBaseUrl ?? ''));
        $payload = $this->buildEmpresaPayload($empresa);
        $contadorDoc = $this->formatDocumento((string) $contador->cnpj_cpf);
        // Portal espera cnpjContador; mantém contadorCnpj por compatibilidade com a spec.
        $payload['cnpjContador'] = $contadorDoc;
        $payload['contadorCnpj'] = $contadorDoc;
        if (filled($contador->email)) {
            $email = trim((string) $contador->email);
            $payload['emailContador'] = $email;
            $payload['contadorEmail'] = $email;
        }

        try {
            $response = LicencaHttpClient::make()
                ->timeout((int) config('contador-cloud.default_timeout', 30))
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $json = ContadorCloudHttpHelper::isJsonApiResponse($response) ? $response->json() : null;
            $errorCode = is_array($json) ? (string) ($json['error'] ?? $json['code'] ?? '') : '';

            if ($errorCode === 'contador_nao_encontrado') {
                return [
                    'ok' => false,
                    'message' => 'Contador não encontrado no portal. Cadastre o escritório no portal primeiro.',
                    'data' => is_array($json) ? $json : null,
                    'code' => 'contador_nao_encontrado',
                ];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return [
                    'ok' => false,
                    'message' => 'Segredo de auto-vínculo rejeitado; confira ERP_AUTO_VINCULO_SECRET.',
                    'data' => is_array($json) ? $json : null,
                    'code' => 'auto_secret_rejeitado',
                ];
            }

            if ($response->status() === 404 || ! ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                return [
                    'ok' => false,
                    'message' => 'Portal ainda não disponibilizou o vínculo automático.',
                    'data' => null,
                    'code' => 'auto_indisponivel',
                ];
            }

            if (! $response->successful()) {
                $error = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? '') : trim($response->body());
                $details = '';
                if (is_array($json['details'] ?? null) && $json['details'] !== []) {
                    $first = $json['details'][0] ?? null;
                    if (is_array($first)) {
                        $path = is_array($first['path'] ?? null) ? implode('.', $first['path']) : '';
                        $details = trim(($path !== '' ? $path.': ' : '').(string) ($first['message'] ?? ''));
                    }
                }

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'Portal respondeu: '.$error.($details !== '' ? ' ('.$details.')' : '')
                        : 'Portal respondeu com status '.$response->status().'.',
                    'data' => is_array($json) ? $json : null,
                    'code' => $errorCode !== '' ? $errorCode : 'http_'.$response->status(),
                ];
            }

            if (! is_array($json)) {
                return [
                    'ok' => false,
                    'message' => 'Resposta inválida do portal no vínculo automático.',
                    'data' => null,
                    'code' => 'invalid_response',
                ];
            }

            $credenciais = is_array($json['credenciais'] ?? null)
                ? $json['credenciais']
                : (is_array($json['data']['credenciais'] ?? null) ? $json['data']['credenciais'] : null);

            if ($credenciais === null) {
                return [
                    'ok' => false,
                    'message' => 'Portal não devolveu credenciais no vínculo automático.',
                    'data' => $json,
                    'code' => 'sem_credenciais',
                ];
            }

            $json['status'] = 'authorized';
            $json['credenciais'] = $credenciais;

            if (blank($credenciais['token'] ?? null)) {
                return [
                    'ok' => false,
                    'message' => 'Portal autorizou, mas não enviou token.',
                    'data' => $json,
                    'code' => 'sem_token',
                ];
            }

            return [
                'ok' => true,
                'message' => 'Empresa vinculada automaticamente ao portal.',
                'data' => $json,
                'code' => 'authorized',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Não foi possível contactar o portal: '.$exception->getMessage(),
                'data' => null,
                'code' => 'exception',
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, data: ?array}
     */
    public function solicitarVinculo(Empresa $empresa, ?string $portalBaseUrl = null): array
    {
        $endpoint = ContadorCloudHttpHelper::pairingRequestUrl((string) ($portalBaseUrl ?? ''));

        try {
            $response = LicencaHttpClient::make()
                ->timeout((int) config('contador-cloud.default_timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $this->buildEmpresaPayload($empresa));

            if ($response->status() === 404) {
                return [
                    'ok' => false,
                    'message' => 'O portal ainda não disponibilizou o endpoint de vínculo. '
                        .'Envie docs/portal-contador-vinculo-api.md para o time do portal.',
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
            $response = LicencaHttpClient::make()
                ->timeout((int) config('contador-cloud.default_timeout', 30))
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
            'cnpj' => $this->formatDocumento((string) $empresa->cnpj),
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

    private function formatDocumento(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }
}
