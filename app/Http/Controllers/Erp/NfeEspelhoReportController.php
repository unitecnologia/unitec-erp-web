<?php

namespace App\Http\Controllers\Erp;

use App\Models\Nfe;
use App\Support\Erp\Nfe\NfeEspelhoReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NfeEspelhoReportController
{
    public function __invoke(Request $request, Nfe $nfe, NfeEspelhoReportService $service): View|Response
    {
        abort_unless(Auth::check(), 403);

        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            abort(404);
        }

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nfe->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $data = $service->buildViewData($nfe);
        $data['autoPrint'] = $request->boolean('auto');
        $data['embedded'] = $request->boolean('embed');

        $filename = 'espelho-nfe-' . preg_replace('/\D/', '', (string) $nfe->numero) . '.pdf';

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.nfe-espelho-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->stream($filename);
        }

        return view('reports.nfe-espelho', $data);
    }
}
