<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\Recibo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Printing\Documents\ReciboCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Monta ESC/POS do recibo em bobina para o Device Service. */
class ReciboEscPosPrintController
{
    public function __invoke(Request $request, Recibo $recibo): JsonResponse
    {
        abort_unless(ErpAccess::currentCan('recibos.print'), 403);

        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');

        $document = new ReciboCupomPrintDocument(
            recibo: $recibo,
            empresa: $empresa,
        );

        return response()->json($document->buildEscPosPayload($target));
    }
}
