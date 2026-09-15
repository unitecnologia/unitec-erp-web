<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\UnitecOsDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $device = UnitecOsDevice::query()
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        if ($device === null) {
            $device = new UnitecOsDevice();
            $device->device_uuid = $data['device_uuid'];
            $device->status = UnitecOsDevice::STATUS_PENDENTE;
            $device->pairing_code = $this->generateCode();
            $device->registered_at = now();
        } elseif ($device->revoked_at !== null) {
            // Revogado → novo pareamento (mesmo UUID).
            $device->status = UnitecOsDevice::STATUS_PENDENTE;
            $device->revoked_at = null;
            $device->approved_at = null;
            $device->approved_by = null;
            $device->pairing_code = $this->generateCode();
            $device->registered_at = now();
        } elseif (blank($device->pairing_code)) {
            $device->pairing_code = $this->generateCode();
        }
        // Aprovado ou pendente existente: NÃO regenera pairing_code.

        $device->device_name = $data['device_name'] ?? $device->device_name;
        $device->platform = $data['platform'] ?? $device->platform;
        $device->app_version = $data['app_version'] ?? $device->app_version;
        $device->last_seen_at = now();

        // Dev local: libera na hora (evita reautorizar a cada flutter run).
        $autoApprove = (bool) config('unitec.os_auto_approve_devices', false)
            || app()->environment('local');
        if ($autoApprove && ! $device->isApproved()) {
            $device->status = UnitecOsDevice::STATUS_APROVADO;
            $device->revoked_at = null;
            $device->approved_at = $device->approved_at ?? now();
        }

        $device->save();

        return response()->json([
            'status' => $device->status,
            'pairing_code' => $device->pairing_code,
            'device_name' => $device->device_name,
            'approved' => $device->isApproved(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $uuid = (string) $request->query('device_uuid', (string) $request->header('X-OS-Device', ''));

        if ($uuid === '') {
            return response()->json(['status' => 'desconhecido', 'approved' => false]);
        }

        $device = UnitecOsDevice::query()->where('device_uuid', $uuid)->first();

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

            $exists = UnitecOsDevice::query()
                ->where('pairing_code', $code)
                ->whereNull('revoked_at')
                ->where('status', UnitecOsDevice::STATUS_PENDENTE)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
