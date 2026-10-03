<?php

namespace App\Http\Controllers\Erp;

use App\Models\Orcamento;
use App\Support\Erp\Orcamento\OrcamentoBobinaBuilder;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrcamentoReportController
{
    public function __invoke(Request $request, Orcamento $orcamento): View|Response
    {
        abort_unless(Auth::user(), 403);

        $report = app(OrcamentoReportService::class);
        $bobina = $request->boolean('bobina');
        $data = $report->buildViewData($orcamento);
        $data['autoPrint'] = $request->boolean('auto');
        $data['embedded'] = $request->boolean('embed');
        $data['bobina'] = $bobina;

        if ($bobina) {
            $data['bobinaLines'] = app(OrcamentoBobinaBuilder::class)->buildLines(
                $data['orcamento'],
                $data['empresa'],
                $data['numero'],
                $data['statusLabel'],
                $data['empresaEndereco'],
                $data['totais'],
            );
        }

        if ($request->boolean('pdf')) {
            if ($bobina) {
                $height = max(600, (count($data['bobinaLines']) + 4) * 14);

                return Pdf::loadView('reports.orcamento-bobina-pdf', $data)
                    ->setPaper([0, 0, 226.77, $height], 'portrait')
                    ->download('orcamento-bobina-' . $data['numero'] . '.pdf');
            }

            return Pdf::loadView('reports.orcamento-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->download('orcamento-' . $data['numero'] . '.pdf');
        }

        return view($bobina ? 'reports.orcamento-bobina' : 'reports.orcamento', $data);
    }
}
