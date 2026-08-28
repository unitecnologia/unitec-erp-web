<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\Printing\Documents\PdvMovimentoCaixaCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Monta ESC/POS do comprovante Sangria/Suprimento para o Device Service. */
class PdvMovimentoCaixaEscPosPrintController
{
    public function __invoke(Request $request, PdvCaixaMovimento $movimento): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $tipo = mb_strtolower(trim((string) $movimento->tipo), 'UTF-8');
        abort_unless(in_array($tipo, ['sangria', 'suprimento'], true), 404);

        $movimento->load(['sessao.user', 'sessao.terminal', 'sessao.empresa']);

        $sessao = $movimento->sessao;
        abort_unless($sessao, 404);

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

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');

        $document = new PdvMovimentoCaixaCupomPrintDocument(
            movimento: $movimento,
            empresa: $empresa,
            usuarioFallback: $user->name ?? null,
        );

        return response()->json($document->buildEscPosPayload($target));
    }
}
