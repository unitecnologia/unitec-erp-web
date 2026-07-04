<?php

namespace App\Support\ContadorCloud;

use Illuminate\Support\Facades\Http;

class ContadorCloudClient
{
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

        try {
            $response = Http::timeout($config->timeout)
                ->withToken($config->token)
                ->acceptJson()
                ->get($config->healthUrl());

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Conexão com o Portal do Contador estabelecida com sucesso.',
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
