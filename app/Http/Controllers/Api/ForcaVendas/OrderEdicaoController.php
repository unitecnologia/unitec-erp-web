<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\User;
use App\Support\ForcaVendas\ForcaVendasEdicaoLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderEdicaoController
{
    public function __construct(private readonly ForcaVendasEdicaoLockService $locks)
    {
    }

    /**
     * Inicia edição no App FV: exige conexão e confirma bloqueio no ERP.
     */
    public function lock(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deviceUuid = trim((string) $request->header('X-FV-Device', ''));
        if ($deviceUuid === '') {
            $deviceUuid = trim((string) $request->input('device_uuid', ''));
        }

        $result = $this->locks->adquirir($uuid, $deviceUuid, $user);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Não foi possível bloquear a edição.',
                'code' => $result['code'] ?? 'lock_failed',
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'locked' => true,
            'label' => $result['label'] ?? ForcaVendasEdicaoLockService::LABEL,
            'ja_possuia' => $result['ja_possuia'] ?? false,
        ]);
    }

    /**
     * Cancela edição: libera bloqueio somente com confirmação do servidor (mesmo aparelho).
     */
    public function unlock(Request $request, string $uuid): JsonResponse
    {
        $deviceUuid = trim((string) $request->header('X-FV-Device', ''));
        if ($deviceUuid === '') {
            $deviceUuid = trim((string) $request->input('device_uuid', ''));
        }

        $result = $this->locks->liberar($uuid, $deviceUuid);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Não foi possível liberar a edição.',
                'code' => $result['code'] ?? 'unlock_failed',
            ], 409);
        }

        return response()->json(['ok' => true]);
    }
}
