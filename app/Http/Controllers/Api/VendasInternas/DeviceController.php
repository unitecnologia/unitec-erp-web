<?php

namespace App\Http\Controllers\Api\VendasInternas;

use App\Models\VendasInternasDevice;
use App\Models\VendasInternasDeviceReset;
use App\Support\VendasInternas\VendasInternasDeviceResetService;
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
        } elseif ($device->revoked_at !== null && $device->status === VendasInternasDevice::STATUS_APROVADO) {
            $device->revoked_at = null;
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

    /**
     * Reset da base local autorizado pelo retaguarda (consulta na próxima conexão).
     */
    public function resetStatus(Request $request, VendasInternasDeviceResetService $resets): JsonResponse
    {
        $uuid = $this->deviceUuidDaRequisicao($request);
        $reset = $resets->pendente($uuid);

        if ($reset === null) {
            return response()->json(['pending' => false]);
        }

        return response()->json([
            'pending' => true,
            'reset_uuid' => $reset->uuid,
            'authorized_at' => $reset->authorized_at?->toIso8601String(),
        ]);
    }

    /**
     * O app confirma que apagou a base local. Só o próprio aparelho conclui.
     */
    public function resetConcluir(Request $request, string $reset, VendasInternasDeviceResetService $resets): JsonResponse
    {
        $request->validate([
            'device_uuid' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $resets->concluir(
            $reset,
            $this->deviceUuidDaRequisicao($request),
            $request->input('app_version'),
        );

        if (! $result['ok']) {
            return response()->json([
                'message' => 'Reset não encontrado ou não pertence a este aparelho.',
                'code' => 'reset_'.$result['erro'],
            ], $result['erro'] === 'nao_encontrado' ? 404 : 409);
        }

        return response()->json([
            'ok' => true,
            'status' => VendasInternasDeviceReset::STATUS_CONCLUIDO,
            'ja_concluido' => $result['ja_concluido'] ?? false,
        ]);
    }

    private function deviceUuidDaRequisicao(Request $request): string
    {
        $header = trim((string) $request->header('X-VI-Device', ''));

        return $header !== ''
            ? $header
            : trim((string) $request->input('device_uuid', $request->query('device_uuid', '')));
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
