<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Pdv\PdvNfceCancelamentoProtocoloService;
use App\Support\Erp\Printing\Documents\NfceCancelamentoProtocoloCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Monta ESC/POS do protocolo de cancelamento NFC-e para o Device Service. */
class NfceCancelamentoProtocoloEscPosPrintController
{
    public function __invoke(
        Request $request,
        PdvVenda $venda,
        PdvNfceCancelamentoProtocoloService $service,
    ): JsonResponse {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless($venda->fiscal, 404);

        $venda->load(['sessao', 'nfce']);

        abort_unless(
            $venda->nfce !== null && filled($venda->nfce->protocolo_cancelamento),
            404,
        );

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        NfceEmpresaEscopo::abortSeVendaForaDaEmpresa($venda, $empresaId ? (int) $empresaId : null);

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');
        abort_if($target->impressoraA4(), 422, 'Terminal configurado para impressão A4: use a impressão pelo navegador.');

        $document = new NfceCancelamentoProtocoloCupomPrintDocument(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) $user->name,
        );

        return response()->json($document->buildEscPosPayload($target, $service));
    }
}
