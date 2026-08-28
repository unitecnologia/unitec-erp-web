<?php

namespace App\Support\Erp\Backup;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PortalBkpReporter
{
    public function report(Empresa $empresa, string $status, ?string $details = null): void
    {
        $token = trim((string) ($empresa->param_portal_bkp_token ?? ''));
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?: '';

        if ($token === '' || strlen($cnpj) !== 14) {
            return;
        }

        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url', 'https://unitecnologiasc.digital'), '/');

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeader('X-BKP-Token', $token)
                ->post($baseUrl.'/api/bkp/'.$cnpj, [
                    'status' => $status === 'ok' ? 'sucesso' : 'falha',
                    'detalhes' => $details !== null ? mb_substr($details, 0, 1000) : null,
                ]);

            if (! $response->successful()) {
                Log::warning('Portal BKP recusou o resultado do backup.', [
                    'empresa_id' => $empresa->id,
                    'status_http' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            // Comunicação com portal nunca pode invalidar o backup local.
            Log::warning('Não foi possível informar backup ao portal.', [
                'empresa_id' => $empresa->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
