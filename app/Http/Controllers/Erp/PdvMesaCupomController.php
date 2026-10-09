<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvMesa;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Printing\Documents\PdvMesaCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Pedido / item / pré-conta da mesa: HTML 80mm (navegador) e ESC/POS (Device Service). */
class PdvMesaCupomController
{
    public function report(Request $request, PdvMesa $mesa, string $tipo): View
    {
        $document = $this->document($request, $mesa, $tipo);
        $layout = $document->layout();

        abort_if($layout === null, 404, 'Mesa sem itens para imprimir.');

        return view('reports.pdv-cupom', [
            'autoPrint' => $request->boolean('auto'),
            'copias' => max(1, min(3, (int) $request->query('copias', 1))),
            'layout' => $layout,
        ]);
    }

    public function escpos(Request $request, PdvMesa $mesa, string $tipo): JsonResponse
    {
        $document = $this->document($request, $mesa, $tipo);

        $target = PrintFacade::targetFromTerminal((int) $request->query('copias', 1));
        abort_unless($target->useDeviceService, 422, 'Device Service desativado neste terminal.');
        abort_unless($target->hasPrinter(), 422, 'Configure a impressora Windows no Terminal.');
        abort_if($target->impressoraA4(), 422, 'Terminal configurado para impressão A4: use a impressão pelo navegador.');

        $payload = $document->buildEscPosPayload($target);
        abort_if($payload === null, 404, 'Mesa sem itens para imprimir.');

        return response()->json($payload);
    }

    private function document(Request $request, PdvMesa $mesa, string $tipo): PdvMesaCupomPrintDocument
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless(PdvMesaCupomPrintDocument::tipoValido($tipo), 404);

        $empresaId = (int) session('erp_empresa_id', $user->empresa_id);
        abort_unless($empresaId > 0 && (int) $mesa->empresa_id === $empresaId, 403);

        $item = $request->query('item');

        return new PdvMesaCupomPrintDocument(
            mesa: $mesa,
            tipo: $tipo,
            itemIndex: is_numeric($item) ? max(0, (int) $item) : null,
            empresa: Empresa::query()->find($empresaId),
            atendente: (string) $user->name,
            terminal: trim((string) (PdvConfig::make()->terminal()?->nome ?? '')),
        );
    }
}
