<?php

namespace App\Http\Controllers\Erp;

use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\ErpUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErpUpdateController
{
    public function launch(Request $request): JsonResponse
    {
        if (! Auth::check() && ! (function_exists('filament') && filament()->auth()->check())) {
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente e tente atualizar.',
            ], 401);
        }

        ErpUpdateService::clearStaleLock();

        if (ErpUpdateService::isRunning()) {
            return response()->json([
                'message' => 'Já existe uma atualização em andamento.',
            ], 409);
        }

        ErpUpdateService::resetStatus();

        if (! ErpUpdateProcessLauncher::launch(base_path())) {
            ErpUpdateService::forceReset();

            return response()->json([
                'message' => 'Não foi possível iniciar o processo de atualização. Verifique storage/logs/erp-update-spawn.log e instalacao.log.',
            ], 500);
        }

        return response()->json([
            'message' => 'Atualização iniciada.',
        ]);
    }

    /**
     * Sem auth: durante a atualização a sessão pode cair e o modal precisa continuar lendo o progresso.
     */
    public function status(): JsonResponse
    {
        ErpUpdateService::clearStaleLock(300);

        return response()->json(ErpUpdateService::readStatus());
    }

    /**
     * Sem auth: permite limpar estado travado com "Unauthenticated" / lock velho.
     */
    public function reset(Request $request): JsonResponse
    {
        ErpUpdateService::forceReset();

        return response()->json([
            'message' => 'Estado de atualização limpo.',
        ]);
    }
}
