<?php

namespace App\Http\Middleware;

use App\Models\VendasInternasDevice;
use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        if (
            Schema::hasColumn('terminais', 'device_uuid')
            && $device->empresa_id
            && ! Terminal::query()
                ->where('empresa_id', $device->empresa_id)
                ->where('device_uuid', $uuid)
                ->where('ativo', true)
                ->exists()
        ) {
            return response()->json([
                'message' => 'Aparelho desativado em Configurações → Terminais.',
                'code' => 'device_terminal_inactive',
            ], 403);
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}
