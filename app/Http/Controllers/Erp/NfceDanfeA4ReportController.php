<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Nfce\NfceDanfeA4Data;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** DANFE NFC-e em A4 (terminal com tipo de impressora "NFC-e - A4"): navegador ou PDF. */
class NfceDanfeA4ReportController
{
    public function __invoke(Request $request, PdvVenda $venda, NfceDanfeA4Data $data): Response
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($venda->fiscal, 404);

        $venda->load(['itens.product', 'pagamentos', 'person', 'sessao', 'nfce', 'venda', 'vendedor']);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        NfceEmpresaEscopo::abortSeVendaForaDaEmpresa($venda, $empresaId ? (int) $empresaId : null);
        NfceImpressaoFiscal::abortSeBloqueada($venda);

        $pdf = $request->boolean('pdf');
        $viewData = array_merge(
            $data->build(
                venda: $venda,
                empresa: $empresa,
                usuario: (string) $user->name,
                operacao: (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR),
                copias: max(1, min(3, (int) $request->query('copias', 1))),
                autoPrint: ! $pdf && $request->boolean('auto'),
            ),
            [
                'pdf' => $pdf,
                'embed' => $request->boolean('embed'),
            ],
        );

        if ($pdf) {
            $numero = preg_replace('/\D/', '', (string) ($viewData['numeroNf'] ?? '')) ?: (string) $venda->id;

            return Pdf::loadView('reports.nfce-danfe-a4', $viewData)
                ->setPaper('a4', 'portrait')
                ->stream('NFCE-'.$numero.'.pdf');
        }

        return response()->view('reports.nfce-danfe-a4', $viewData);
    }
}
