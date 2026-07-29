<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Printing\Documents\PdvCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Monta ESC/POS do cupom PDV (não fiscal) para o Device Service. */
class PdvEscPosPrintController
{
    public function __invoke(Request $request, PdvVenda $venda): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $venda->load(['itens.product', 'pagamentos', 'person', 'sessao']);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            abort_unless(
                (int) $venda->sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');

        $document = new PdvCupomPrintDocument(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) $user->name,
        );

        return response()->json($document->buildEscPosPayload($target));
    }
}
