<?php

namespace App\Http\Controllers\Api\Pdv;

use App\Support\Pdv\PdvCargaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Verificação leve de licença/terminal para o login do PDV offline.
     * A validação real (vaga, terminal ativo) ocorre no middleware pdv.terminal.ativo.
     */
    public function licenca(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        /** @var \App\Models\Terminal|null $terminal */
        $terminal = $request->attributes->get('pdv_terminal');

        if (! $terminal) {
            return response()->json(['message' => 'Terminal não resolvido.'], 422);
        }

        return response()->json([
            'ok' => true,
            'empresa_id' => $empresaId,
            'terminal' => [
                'id' => (int) $terminal->id,
                'nome' => (string) ($terminal->nome ?? ''),
                'ativo' => (bool) ($terminal->ativo ?? false),
            ],
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $terminal = trim((string) $request->query('terminal', ''));
        $terminal = $terminal !== '' ? $terminal : null;

        $productsAfterId = max(0, (int) $request->query('products_after_id', 0));
        $productsLimit = (int) $request->query('products_limit', PdvCargaService::PRODUCTS_PAGE_DEFAULT);
        if ($productsLimit < 1) {
            $productsLimit = PdvCargaService::PRODUCTS_PAGE_DEFAULT;
        }

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);

            // ETag só na 1ª página (páginas seguintes mudam o cursor).
            if ($productsAfterId === 0) {
                $signature = $this->service->pullSignature($empresaId, $terminal);
                $ifNoneMatch = trim((string) $request->header('If-None-Match'), '"');

                if ($ifNoneMatch !== '' && hash_equals($signature, $ifNoneMatch)) {
                    return response()->json(null, 304)->setEtag($signature);
                }
            } else {
                $signature = null;
            }

            $since = null;
            $sinceRaw = $request->query('since');

            if (is_string($sinceRaw) && $sinceRaw !== '') {
                try {
                    $since = Carbon::parse($sinceRaw);
                } catch (Throwable) {
                    $since = null;
                }
            }

            $sinceByEntity = $this->parseSinceByEntity($request, $since);

            $payload = $this->service->buildPull(
                $since,
                $empresaId,
                $terminal,
                $productsAfterId,
                $productsLimit,
                $sinceByEntity,
            );

            $response = response()->json($payload);

            return $signature !== null ? $response->setEtag($signature) : $response;
        } catch (Throwable $e) {
            Log::error('PDV carga/pull falhou.', [
                'empresa_id' => $empresaId,
                'terminal' => $terminal,
                'products_after_id' => $productsAfterId,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => 'Falha ao montar a carga do PDV. Verifique o log do ERP (carga/pull).',
            ], 500);
        }
    }

    /**
     * Certificado A1 (.pfx) + senha criptografados (AES-256-GCM) para o PDV
     * assinar NFC-e offline. Baixado sob demanda quando o fingerprint muda.
     */
    public function certificado(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

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

    /**
     * Empresa do PDV: a mesma do middleware (engrenagem), em pull e certificado.
     */
    private function empresaId(Request $request): int
    {
        return (int) ($request->attributes->get('pdv_empresa_id')
            ?: $request->query('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 1);
    }

    /**
     * @return array<string, Carbon|null>|null
     */
    private function parseSinceByEntity(Request $request, ?Carbon $fallbackSince): ?array
    {
        $map = [
            'products' => 'since_products',
            'customers' => 'since_customers',
            'formas_pagamento' => 'since_formas_pagamento',
            'users' => 'since_users',
        ];

        $parsed = [];
        $hasSpecific = false;

        foreach ($map as $entity => $param) {
            $raw = $request->query($param);

            if (! is_string($raw) || $raw === '') {
                $parsed[$entity] = $fallbackSince;

                continue;
            }

            try {
                $parsed[$entity] = Carbon::parse($raw);
                $hasSpecific = true;
            } catch (Throwable) {
                $parsed[$entity] = $fallbackSince;
            }
        }

        return $hasSpecific ? $parsed : null;
    }
}
