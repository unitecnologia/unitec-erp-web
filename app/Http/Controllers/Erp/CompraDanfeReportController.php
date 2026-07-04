<?php

namespace App\Http\Controllers\Erp;

use App\Models\Compra;
use App\Support\Erp\Compra\CompraDanfeReportService;
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
