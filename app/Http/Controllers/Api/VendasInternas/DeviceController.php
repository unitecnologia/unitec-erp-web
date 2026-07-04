<?php

namespace App\Http\Controllers\Api\VendasInternas;

use App\Models\VendasInternasDevice;
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

        $device = VendasInternasDevice::query()
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        if ($device === null) {
            $device = new VendasInternasDevice();
            $device->device_uuid = $data['device_uuid'];
            $device->status = VendasInternasDevice::STATUS_PENDENTE;
            $device->pairing_code = $this->generateCode();
            $device->registered_at = now();
        } elseif ($device->revoked_at !== null) {
            $device->status = VendasInternasDevice::STATUS_PENDENTE;
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
        $uuid = (string) $request->query('device_uuid', (string) $request->header('X-VI-Device', ''));

        if ($uuid === '') {
            return response()->json(['status' => 'desconhecido', 'approved' => false]);
        }

        $device = VendasInternasDevice::query()->where('device_uuid', $uuid)->first();

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

            $exists = VendasInternasDevice::query()
                ->where('pairing_code', $code)
                ->whereNull('revoked_at')
                ->where('status', VendasInternasDevice::STATUS_PENDENTE)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
