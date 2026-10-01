<?php

namespace App\Http\Controllers\Erp;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseDanfseViewData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NfseImpressaoReportController
{
    public function __invoke(Request $request, Nfse $nfse): View|Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless($nfse->status === Nfse::STATUS_AUTORIZADA && filled($nfse->xml_nfse), 404);

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nfse->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $embedded = $request->boolean('embed');
        $data = NfseDanfseViewData::for(
            $nfse,
            autoPrint: $request->boolean('auto'),
            embedded: $embedded || $request->boolean('pdf'),
        );

        if ($request->boolean('pdf')) {
            $numero = preg_replace('/\D+/', '', (string) ($nfse->numero_nfse ?: $nfse->numero_dps)) ?: (string) $nfse->id;

            return Pdf::loadView('reports.nfse-impressao', $data)
                ->setPaper('a4', 'portrait')
                ->download('DANFSe-'.$numero.'.pdf');
        }

        return view('reports.nfse-impressao', $data);
    }
}
