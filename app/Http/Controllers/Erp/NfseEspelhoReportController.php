<?php

namespace App\Http\Controllers\Erp;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseEspelhoReportService;
use App\Support\Erp\Nfse\NfseImpressao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NfseEspelhoReportController
{
    public function __invoke(Request $request, Nfse $nfse, NfseEspelhoReportService $service): View|Response
    {
        abort_unless(Auth::check(), 403);

        if ($nfse->status !== Nfse::STATUS_ABERTA) {
            abort(404);
        }

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nfse->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $embedded = $request->boolean('embed');
        $data = $service->buildViewData(
            $nfse,
            autoPrint: $request->boolean('auto'),
            embedded: $embedded || $request->boolean('pdf'),
        );

        $filename = 'espelho-nfse-'.(preg_replace('/\D+/', '', (string) ($nfse->numero_dps ?: $nfse->id)) ?: (string) $nfse->id).'.pdf';

        $view = (string) ($data['impressao_view'] ?? NfseImpressao::VIEW_NACIONAL);

        if ($request->boolean('pdf')) {
            return Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->stream($filename);
        }

        return view($view, $data);
    }
}
