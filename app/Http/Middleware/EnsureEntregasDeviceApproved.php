<?php

namespace App\Http\Middleware;

use App\Models\EntregasDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntregasDeviceApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = (string) $request->header('X-ENT-Device', '');

        if ($uuid === '') {
            return response()->json([
                'message' => 'Aparelho não identificado.',
                'code' => 'device_required',
            ], 403);
        }

        $device = EntregasDevice::query()->where('device_uuid', $uuid)->first();

        if ($device === null || ! $device->isApproved()) {
            $code = $device !== null && $device->revoked_at !== null
                ? 'device_revoked'
                : 'device_not_approved';

            return response()->json([
                'message' => 'Aparelho aguardando autorização do administrador.',
                'code' => $code,
            ], 403);
        }

        $device->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('entregas_device', $device);

        return $next($request);
    }
}
