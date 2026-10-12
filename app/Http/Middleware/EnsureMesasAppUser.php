<?php

namespace App\Http\Middleware;

use App\Models\MesasDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Depois do auth:sanctum: o token precisa ser do Unitec Mesas e deste aparelho,
 * e o garçom continuar ativo, com acesso ao app e empresa definida.
 */
class EnsureMesasAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $device = $request->attributes->get('mesas_device');

        $tokenValido = $user instanceof User
            && $token !== null
            && method_exists($token, 'can')
            && $token->can('mesas')
            && $device instanceof MesasDevice
            && (string) ($token->name ?? '') === 'mesas:'.$device->device_uuid;

        if (! $tokenValido) {
            return response()->json([
                'message' => 'Sessão inválida para este aparelho. Entre novamente.',
                'code' => 'session_invalid',
            ], 401);
        }

        if (! $user->ativo || ! $user->podeAcessarApp(User::APP_MESAS) || (int) ($user->empresa_id ?? 0) <= 0) {
            return response()->json([
                'message' => 'Usuário sem acesso ao Unitec Mesas.',
                'code' => 'access_denied',
            ], 403);
        }

        return $next($request);
    }
}
