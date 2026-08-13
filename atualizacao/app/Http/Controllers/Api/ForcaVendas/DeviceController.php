<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\ForcaVendasDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController
{
    /**
     * Registro do aparelho na rede (sem segredo). Cria/atualiza um aparelho
     * com status "pendente"; o administrador autoriza no ERP. Retorna o codigo
     * de pareamento para o vendedor conferir na lista do ERP.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $device = ForcaVendasDevice::query()
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        if ($device === null) {
            $device = new ForcaVendasDevice();
            $device->device_uuid = $data['device_uuid'];
            $device->status = ForcaVendasDevice::STATUS_PENDENTE;
            $device->pairing_code = $this->generateCode();
            $device->registered_at = now();
        } elseif ($device->revoked_at !== null && $device->status === ForcaVendasDevice::STATUS_APROVADO) {
            // Legado: logout antigo marcava revoked_at sem o admin ter revogado.
            // Restaura autorização sem pedir F2 de novo.
            $device->revoked_at = null;
        } elseif ($device->revoked_at !== null) {
            // Admin revogou (status revogado/pendente): volta para a fila.
            $device->status = ForcaVendasDevice::STATUS_PENDENTE;
            $device->revoked_at = null;
            $device->pairing_code = $this->generateCode();
            $device->registered_at = now();
        } elseif (blank($device->pairing_code)) {
            $device->pairing_code = $this->generateCode();
        }

        $device->device_name = $data['device_name'] ?? $device->device_name;
        $device->platform = $data['platform'] ?? $device->platform;
        $device->app_version = $data['app_version'] ?? $device->app_version;
        $device->last_seen_at = now();

        $isNew = ! $device->exists;
        $statusMudou = $device->isDirty('status') || $device->isDirty('revoked_at');
        $device->save();

        if (
            $device->status === ForcaVendasDevice::STATUS_PENDENTE
            && ($isNew || $statusMudou)
        ) {
            try {
                app(\App\Support\Gestor\GestorPushService::class)->notifyAparelhoPendente($device);
            } catch (\Throwable) {
                // ignore
            }
        }

        return response()->json([
            'status' => $device->status,
            'pairing_code' => $device->pairing_code,
            'device_name' => $device->device_name,
            'approved' => $device->isApproved(),
        ]);
    }

    /**
     * Consulta o status de autorizacao do aparelho (polling pelo app).
     */
    public function status(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('device_uuid', (string) $request->header('X-FV-Device', ''));

        if ($uuid === '') {
            return response()->json(['status' => 'desconhecido', 'approved' => false]);
        }

        $device = ForcaVendasDevice::query()->where('device_uuid', $uuid)->first();

        if ($device === null) {
            return response()->json(['status' => 'desconhecido', 'approved' => false]);
        }

        if ($device->revoked_at !== null) {
            return response()->json([
                'status' => 'revogado',
                'approved' => false,
                'pairing_code' => $device->pairing_code,
            ]);
        }

        return response()->json([
            'status' => $device->status,
            'approved' => $device->isApproved(),
            'pairing_code' => $device->pairing_code,
            'device_name' => $device->device_name,
        ]);
    }

    private function generateCode(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $exists = ForcaVendasDevice::query()
                ->where('pairing_code', $code)
                ->whereNull('revoked_at')
                ->where('status', ForcaVendasDevice::STATUS_PENDENTE)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
