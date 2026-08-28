<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\Printing\Documents\PdvCaixaResumoCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Monta ESC/POS do RESUMO CAIXA para o Device Service. */
class PdvCaixaResumoEscPosPrintController
{
    public function __invoke(Request $request, PdvCaixaSessao $sessao): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = session('erp_empresa_id', $user->empresa_id);

        if (filled($sessao->empresa_id)) {
            abort_unless(
                (int) $sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $empresa = $sessao->empresa_id
            ? Empresa::query()->find($sessao->empresa_id)
            : ($empresaId ? Empresa::query()->find($empresaId) : $user->empresa);

        $dinheiroInformado = (float) $request->query('dinheiro', 0);

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');

        $document = new PdvCaixaResumoCupomPrintDocument(
            sessao: $sessao,
            empresa: $empresa,
            dinheiroInformado: $dinheiroInformado,
            usuarioFallback: $user->name ?? null,
        );

        return response()->json($document->buildEscPosPayload($target));
    }
}
