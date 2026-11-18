<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\VendasInternasDevice;
use App\Support\VendasInternas\VendasInternasDeviceVinculo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas autenticadas do Vendas Internas: só o usuário vinculado ao aparelho
 * (vendas_internas_devices.user_id) usa a API. Roda depois de vendasinternas.device e auth:sanctum.
 */
class EnsureVendasInternasDeviceUser
{
    public function __construct(private readonly VendasInternasDeviceVinculo $vinculo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $device = $request->attributes->get('vi_device');

        if (! $device instanceof VendasInternasDevice) {
            $device = VendasInternasDevice::query()
                ->where('device_uuid', (string) $request->header('X-VI-Device', ''))
                ->first();
        }

        if (! $user instanceof User || $device === null) {
            return response()->json([
                'message' => VendasInternasDeviceVinculo::MSG_OUTRO_USUARIO,
                'code' => VendasInternasDeviceVinculo::CODE_OUTRO_USUARIO,
            ], 403);
        }

        $code = $this->vinculo->recusaParaSessao($device, $user);

        if ($code !== null) {
            return response()->json([
                'message' => VendasInternasDeviceVinculo::mensagem($code),
                'code' => $code,
            ], 403);
        }

        return $next($request);
    }
}
