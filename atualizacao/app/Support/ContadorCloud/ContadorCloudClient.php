<?php

namespace App\Support\ContadorCloud;

use Illuminate\Support\Facades\Http;

class ContadorCloudClient
{
    /**
     * @return array{ok: bool, message: string, http_status: ?int, response: ?array}
     */
    public function syncDocumento(ContadorCloudConfig $config, array $payload): array
    {
        if (! $config->isActive()) {
            return [
                'ok' => false,
                'message' => 'Portal do Contador não está habilitado ou configurado.',
                'http_status' => null,
                'response' => null,
            ];
        }

        $endpoint = $config->syncUrl();

        try {
            $response = Http::timeout($config->timeout)
                ->withToken($config->token)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if ($response->successful() && ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                return [
                    'ok' => true,
                    'message' => 'Documento enviado ao Portal do Contador.',
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ];
            }

            if ($response->successful()) {
                return [
                    'ok' => false,
                    'message' => ContadorCloudHttpHelper::invalidApiMessage($response, $endpoint),
                    'http_status' => $response->status(),
                    'response' => null,
                ];
            }

            $body = trim($response->body());

            return [
                'ok' => false,
                'message' => $body !== ''
                    ? 'A API respondeu com status '.$response->status().': '.mb_substr($body, 0, 300)
                    : 'A API respondeu com status '.$response->status().'.',
                'http_status' => $response->status(),
                'response' => ContadorCloudHttpHelper::isJsonApiResponse($response) ? $response->json() : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Falha ao enviar documento: '.$exception->getMessage(),
                'http_status' => null,
                'response' => null,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(ContadorCloudConfig $config): array
    {
        if (! $config->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Preencha URL da API, ID da empresa na nuvem e token antes de testar.',
            ];
        }

        $endpoint = $config->syncUrl();

        try {
            $response = Http::timeout($config->timeout)
                ->withToken($config->token)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'cnpj' => '00.000.000/0000-00',
                    'tipo' => ContadorCloudDocumentPayloadBuilder::PORTAL_TIPO_NF_EMITIDA,
                    'numero' => '0',
                    'dataEmissao' => now()->format('Y-m-d'),
                    'competencia' => now()->format('Y-m'),
                ]);

            if ($response->successful() && ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                return [
                    'ok' => true,
                    'message' => 'Conexão com a API do Portal do Contador estabelecida com sucesso.',
                ];
            }

            if (ContadorCloudHttpHelper::isJsonApiResponse($response)) {
                $json = $response->json();
                $error = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? '') : '';

                if ($response->status() === 401 || str_contains(strtolower($error), 'token')) {
                    return [
                        'ok' => false,
                        'message' => 'A API foi alcançada, porém o token está inválido ou a empresa está inativa no portal. '
                            .'Gere um novo token no portal e confira o cadastro da empresa.',
                    ];
                }

                if (in_array($response->status(), [400, 422], true)) {
                    return [
                        'ok' => true,
                        'message' => 'Conexão com a API do Portal do Contador estabelecida com sucesso.',
                    ];
                }

                return [
                    'ok' => false,
                    'message' => $error !== ''
                        ? 'A API respondeu: '.$error
                        : 'A API respondeu com status '.$response->status().'.',
                ];
            }

            if ($response->successful()) {
                return [
                    'ok' => false,
                    'message' => ContadorCloudHttpHelper::invalidApiMessage($response, $endpoint),
                ];
            }

            return [
                'ok' => false,
                'message' => 'A API respondeu com status '.$response->status().'. Verifique URL, token e ambiente.',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Não foi possível conectar: '.$exception->getMessage(),
            ];
        }
    }
}
