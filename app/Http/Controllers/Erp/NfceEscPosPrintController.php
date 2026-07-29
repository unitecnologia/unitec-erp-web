<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use App\Support\Erp\Printing\Documents\NfceCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Monta cupom NFC-e em ESC/POS (mike42) para o Device Service imprimir via RAW.
 */
class NfceEscPosPrintController
{
    public function __invoke(
        Request $request,
        PdvVenda $venda,
        PdvNfceSimuladaService $service,
    ): JsonResponse {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless($venda->fiscal, 404);

        $venda->load(['itens.product', 'pagamentos', 'person', 'sessao', 'nfce']);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            abort_unless(
                (int) $venda->sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');

        $document = new NfceCupomPrintDocument(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) $user->name,
            operacao: (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR),
        );

        return response()->json($document->buildEscPosPayload($target, $service));
    }
}
