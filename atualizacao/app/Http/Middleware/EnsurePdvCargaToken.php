<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a carga do mini-PDV offline por um token Bearer compartilhado
 * (config pdv_carga.token). Simples e adequado para operação em LAN; cada
 * caixa guarda o mesmo segredo no seu .env.
 */
class EnsurePdvCargaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('pdv_carga.token', '');

        if ($expected === '') {
            return response()->json([
                'message' => 'Carga do PDV não configurada no servidor (PDV_CARGA_TOKEN ausente).',
            ], 503);
        }

        $provided = (string) $request->bearerToken();

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Token de carga inválido.'], 401);
        }

        return $next($request);
    }
}
