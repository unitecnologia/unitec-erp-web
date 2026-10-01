<?php

namespace App\Http\Controllers\Api\Integracoes\Ailos;

use App\Services\Ailos\AilosAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Callback público do JWT Ailos (x-ailos-authentication).
 *
 * Ailos faz POST application/json nesta URL após o login do cooperado.
 * O schema exato do body ainda pode variar — parsing flexível.
 */
final class AilosAuthCallbackController
{
    public function __construct(private readonly AilosAuthService $auth)
    {
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = array_merge(
                $request->query(),
                is_array($request->json()?->all()) ? $request->json()->all() : [],
                $request->all(),
            );

            Log::info('Ailos auth callback recebido', [
                'keys' => array_keys($payload),
                'query' => $request->query(),
                'content_type' => $request->header('Content-Type'),
                'raw_preview' => mb_substr($request->getContent(), 0, 500),
            ]);

            $jwt = $this->auth->extractJwtFromMixed($payload, $request->getContent());
            if ($jwt === null || $jwt === '') {
                $header = $request->header('x-ailos-authentication')
                    ?? $request->header('X-Ailos-Authentication');
                if (is_string($header) && trim($header) !== '') {
                    $jwt = $header;
                }
            }

            $state = $this->auth->extractStateFromMixed($payload, $request->query('state'));

            if ($jwt !== null && $jwt !== '' && $state !== null && $state !== '') {
                $this->auth->storeJwtFromCallback($state, $jwt);

                // 200 para a Ailos concluir o login sem travar.
                return response()->json(['ok' => true]);
            }

            Log::warning('Ailos auth callback sem jwt/state reconhecíveis', [
                'keys' => array_keys($payload),
                'has_jwt' => filled($jwt),
                'has_state' => filled($state),
            ]);

            // Ainda 200: evita a Ailos segurar o POST de login indefinidamente.
            return response()->json([
                'ok' => false,
                'error' => 'jwt_or_state_missing',
            ]);
        } catch (Throwable $e) {
            Log::error('Ailos auth callback falhou', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'callback_failed',
            ], 500);
        }
    }

    /**
     * Poll do JWT por state (ERP local busca no servidor do callback).
     * Use: GET /api/integracoes/ailos/auth/jwt?state=...
     */
    public function jwt(Request $request): JsonResponse
    {
        $state = trim((string) $request->query('state', ''));
        if ($state === '') {
            return response()->json(['ok' => false, 'error' => 'state_required'], 422);
        }

        $jwt = $this->auth->peekJwtByState($state);

        if ($jwt === null) {
            return response()->json(['ok' => false, 'pending' => true], 404);
        }

        return response()->json([
            'ok' => true,
            'token' => $jwt,
            'jwt' => $jwt,
        ]);
    }
}
