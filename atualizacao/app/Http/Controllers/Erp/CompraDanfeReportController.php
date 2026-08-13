<?php

namespace App\Http\Controllers\Erp;

use App\Models\Compra;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\ErpContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CompraDanfeReportController
{
    public function __invoke(Request $request, Compra $compra, CompraDanfeReportService $service): View|Response
    {
        abort_unless(Auth::check(), 403);

        $empresaId = ErpContext::currentEmpresaId();

        // Só permite imprimir compra da empresa ativa (aceita legado sem empresa).
        abort_if(
            $empresaId !== null
                && $compra->empresa_id !== null
                && (int) $compra->empresa_id !== $empresaId,
            403,
        );

        $data = $service->buildViewData($compra);
        $data['autoPrint'] = $request->boolean('auto');
        $data['embedded'] = $request->boolean('embed');

        $filename = 'danfe-compra-' . preg_replace('/\D/', '', (string) $compra->numero_nota) . '.pdf';

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.compra-danfe-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->stream($filename);
        }

        return view('reports.compra-danfe', $data);
    }
}
