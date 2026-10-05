<?php

namespace App\Http\Middleware;

use App\Models\ForcaVendasDevice;
use App\Models\User;
use App\Support\ForcaVendas\ForcaVendasDeviceVinculo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas autenticadas do Força de Vendas: só o vendedor vinculado ao aparelho
 * (forca_vendas_devices.user_id) usa a API. Roda depois de forcavendas.device e auth:sanctum.
 */
class EnsureForcaVendasDeviceUser
{
    public function __construct(private readonly ForcaVendasDeviceVinculo $vinculo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $device = $request->attributes->get('fv_device');

        if (! $device instanceof ForcaVendasDevice) {
            $device = ForcaVendasDevice::query()
                ->where('device_uuid', (string) $request->header('X-FV-Device', ''))
                ->first();
        }

        if (! $user instanceof User || $device === null) {
            return response()->json([
                'message' => ForcaVendasDeviceVinculo::MSG_OUTRO_VENDEDOR,
                'code' => ForcaVendasDeviceVinculo::CODE_OUTRO_VENDEDOR,
            ], 403);
        }

        $code = $this->vinculo->recusaParaSessao($device, $user);

        if ($code !== null) {
            return response()->json([
                'message' => ForcaVendasDeviceVinculo::mensagem($code),
                'code' => $code,
            ], 403);
        }

        return $next($request);
    }
}
