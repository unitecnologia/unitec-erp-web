<?php

namespace App\Http\Controllers\Erp;

use App\Models\NotaFornecedor;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDanfeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NotaFornecedorDanfeReportController
{
    public function __invoke(
        Request $request,
        NotaFornecedor $nota,
        NotaFornecedorDanfeReportService $service,
    ): View|Response {
        abort_unless(Auth::check(), 403);

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nota->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $data = $service->buildViewData($nota);
        $data['autoPrint'] = $request->boolean('auto');
        $data['embedded'] = $request->boolean('embed');

        $filename = 'danfe-nf-fornecedor-'.preg_replace('/\D/', '', (string) $nota->numero).'.pdf';

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.nota-fornecedor-danfe-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->stream($filename);
        }

        return view('reports.nota-fornecedor-danfe', $data);
    }
}
