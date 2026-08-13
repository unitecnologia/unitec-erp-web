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
        return response()->json([
            'message' => 'Instalação pelo navegador foi desativada. Baixe o pacote e use “Executar atualização manual” (Unitec Atualizador.exe).',
            'manual_only' => true,
            'package' => ErpUpdateService::packageStatusPayload(),
        ], 410);
    }

    public function runManual(Request $request): JsonResponse
    {
        if (! Auth::check() && ! (function_exists('filament') && filament()->auth()->check())) {
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente e tente de novo.',
            ], 401);
        }

        if (ErpUpdateService::isDownloadRunning()) {
            return response()->json([
                'message' => 'Aguarde o download terminar antes de executar a atualização manual.',
                'package' => ErpUpdateService::packageStatusPayload(),
            ], 409);
        }

        if (! ErpUpdateService::isLocalPackageReady()) {
            return response()->json([
                'message' => 'Pacote ainda não foi baixado. Use “Baixar atualização” primeiro.',
                'needs_download' => true,
                'package' => ErpUpdateService::packageStatusPayload(),
            ], 422);
        }

        if (! ErpUpdateProcessLauncher::launchManualUpdater(base_path())) {
            return response()->json([
                'message' => 'Não foi possível abrir o Unitec Atualizador.exe. Verifique se existe em bin\\ e o log storage/logs/erp-update-spawn.log.',
            ], 500);
        }

        return response()->json([
            'message' => 'Atualizador manual iniciado. O sistema será encerrado para aplicar o pacote.',
            'package' => ErpUpdateService::packageStatusPayload(),
        ]);
    }

    public function download(Request $request): JsonResponse
    {
        if (! Auth::check() && ! (function_exists('filament') && filament()->auth()->check())) {
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente e tente baixar.',
            ], 401);
        }

        if (ErpUpdateService::isRunning()) {
            return response()->json([
                'message' => 'Há uma instalação em andamento. Aguarde concluir.',
            ], 409);
        }

        if (ErpUpdateService::isDownloadRunning()) {
            return response()->json([
                'message' => 'Download já em andamento.',
                'package' => ErpUpdateService::packageStatusPayload(),
            ], 200);
        }

        $force = $request->boolean('force');

        if (! ErpUpdateProcessLauncher::launchDownload(base_path(), $force)) {
            return response()->json([
                'message' => 'Não foi possível iniciar o download. Verifique storage/logs/erp-update-spawn.log.',
            ], 500);
        }

        return response()->json([
            'message' => 'Download iniciado em segundo plano. Você pode continuar usando o sistema.',
            'package' => ErpUpdateService::packageStatusPayload(),
        ]);
    }

    /**
     * Sem auth: durante a atualização a sessão pode cair e o modal precisa continuar lendo o progresso.
     * Também dispara verificação diária se o schedule do Windows não estiver ativo.
     */
    public function status(): JsonResponse
    {
        ErpUpdateService::clearStaleLock(300);

        $this->maybeAutoCheckUpdates();

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
            'package' => ErpUpdateService::packageStatusPayload(),
        ]);
    }

    private function maybeAutoCheckUpdates(): void
    {
        if (ErpUpdateService::isRunning() || ErpUpdateService::isDownloadRunning()) {
            return;
        }

        if (! ErpUpdateService::shouldAutoCheckForUpdates(24)) {
            return;
        }

        // Marca a checagem agora para não disparar várias vezes em sequência de polls.
        ErpUpdateService::writePackageMeta([
            'last_check_at' => now()->toIso8601String(),
            'download_state' => 'checking',
            'check_message' => 'Verificação automática diária iniciada...',
        ]);

        if (! ErpUpdateProcessLauncher::launchDownload(base_path(), false)) {
            ErpUpdateService::writePackageMeta([
                'download_state' => 'idle',
                'check_message' => 'Não foi possível iniciar a verificação automática. Use "Baixar agora".',
                // Permite nova tentativa em ~1h se o spawn falhou.
                'last_check_at' => now()->subHours(23)->toIso8601String(),
            ]);
        }
    }
}
