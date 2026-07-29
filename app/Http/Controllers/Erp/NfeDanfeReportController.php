<?php

namespace App\Http\Controllers\Erp;

use App\Models\Nfe;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NfeDanfeReportController
{
    public function __invoke(Request $request, Nfe $nfe, NfeDanfeReportService $service): View|Response
    {
        abort_unless(Auth::check(), 403);

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nfe->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $data = $service->buildViewData($nfe);
        $data['autoPrint'] = $request->boolean('auto');
        $data['embedded'] = $request->boolean('embed');

        $filename = 'danfe-nfe-' . preg_replace('/\D/', '', (string) $nfe->numero) . '.pdf';

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.nfe-danfe-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->stream($filename);
        }

        return view('reports.nfe-danfe', $data);
    }
}
