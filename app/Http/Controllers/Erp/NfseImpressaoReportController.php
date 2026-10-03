<?php

namespace App\Http\Controllers\Erp;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseImpressao;
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
        $data = NfseImpressao::dados(
            $nfse,
            autoPrint: $request->boolean('auto'),
            embedded: $embedded || $request->boolean('pdf'),
        );
        $view = (string) ($data['impressao_view'] ?? NfseImpressao::VIEW_NACIONAL);

        if ($request->boolean('pdf')) {
            $numero = preg_replace('/\D+/', '', (string) ($nfse->numero_nfse ?: $nfse->numero_dps)) ?: (string) $nfse->id;
            $nome = ($view === NfseImpressao::VIEW_IPM ? 'NFSe-' : 'DANFSe-').$numero.'.pdf';

            return Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->download($nome);
        }

        return view($view, $data);
    }
}
