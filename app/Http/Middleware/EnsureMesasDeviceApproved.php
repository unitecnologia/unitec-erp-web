<?php

namespace App\Http\Middleware;

use App\Models\MesasDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureMesasDeviceApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = (string) $request->header('X-MES-Device', '');

        if ($uuid === '') {
            return response()->json([
                'message' => 'Aparelho não identificado.',
                'code' => 'device_required',
            ], 403);
        }

        if (! Schema::hasTable((new MesasDevice)->getTable())) {
            return response()->json([
                'message' => 'Servidor sem as tabelas do Unitec Mesas. Reinicie o ERP para atualizar o banco.',
                'code' => 'server_not_ready',
            ], 503);
        }

        $device = MesasDevice::query()->where('device_uuid', $uuid)->first();

        if ($device === null || ! $device->isApproved()) {
            $code = $device !== null && $device->revoked_at !== null
                ? 'device_revoked'
                : 'device_not_approved';

            return response()->json([
                'message' => 'Aparelho aguardando autorização do administrador.',
                'code' => $code,
            ], 403);
        }

        // Grava "visto por último" no máximo 1x/min: o app consulta as mesas com frequência.
        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $request->attributes->set('mesas_device', $device);

        return $next($request);
    }
}
