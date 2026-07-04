<?php

namespace App\Http\Middleware;

use App\Models\VendasInternasDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendasInternasDeviceApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = (string) $request->header('X-VI-Device', '');

        if ($uuid === '') {
            return response()->json([
                'message' => 'Aparelho não identificado.',
                'code' => 'device_required',
            ], 403);
        }

        $device = VendasInternasDevice::query()->where('device_uuid', $uuid)->first();

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

        return $next($request);
    }
}
