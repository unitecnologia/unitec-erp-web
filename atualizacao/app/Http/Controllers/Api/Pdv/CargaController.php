<?php

namespace App\Http\Controllers\Api\Pdv;

use App\Support\Pdv\PdvCargaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CargaController
{
    public function __construct(private readonly PdvCargaService $service)
    {
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'pdv-carga',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->query('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 1);

        $terminal = trim((string) $request->query('terminal', ''));
        $terminal = $terminal !== '' ? $terminal : null;

        $signature = $this->service->pullSignature($empresaId, $terminal);

        $ifNoneMatch = trim((string) $request->header('If-None-Match'), '"');

        if ($ifNoneMatch !== '' && hash_equals($signature, $ifNoneMatch)) {
            return response()->json(null, 304)->setEtag($signature);
        }

        $since = null;
        $sinceRaw = $request->query('since');

        if (is_string($sinceRaw) && $sinceRaw !== '') {
            try {
                $since = Carbon::parse($sinceRaw);
            } catch (\Throwable) {
                $since = null;
            }
        }

        $payload = $this->service->buildPull($since, $empresaId, $terminal);

        return response()->json($payload)->setEtag($signature);
    }

    /**
     * Certificado A1 (.pfx) + senha criptografados (AES-256-GCM) para o PDV
     * assinar NFC-e offline. Baixado sob demanda quando o fingerprint muda.
     */
    public function certificado(Request $request): JsonResponse
    {
        $empresaId = (int) ($request->attributes->get('pdv_empresa_id')
            ?: $request->query('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 1);

        /** @var \App\Models\Terminal|null $terminal */
        $terminal = $request->attributes->get('pdv_terminal');

        if (! $terminal) {
            return response()->json(['message' => 'Terminal não resolvido.'], 422);
        }

        $blob = $this->service->certificado($empresaId, (int) $terminal->id);

        if ($blob === null) {
            return response()->json(['message' => 'Certificado não configurado para a empresa.'], 404);
        }

        return response()->json($blob);
    }
}
