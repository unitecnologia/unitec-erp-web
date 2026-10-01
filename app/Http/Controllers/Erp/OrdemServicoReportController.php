<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Support\Erp\Os\OrdemServicoReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrdemServicoReportController
{
    public function __invoke(Request $request, OrdemServico $ordem): View|Response
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        if ($empresaId && (int) ($ordem->empresa_id ?? 0) > 0 && (int) $ordem->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;
        if ($empresa === null && $ordem->empresa_id) {
            $empresa = Empresa::query()->find($ordem->empresa_id);
        }

        $tecnica = $request->boolean('tecnica');
        $data = OrdemServicoReportData::for(
            $ordem,
            $empresa,
            autoPrint: $request->boolean('auto'),
            embedded: $request->boolean('embed'),
            tecnica: $tecnica,
        );

        if ($request->boolean('pdf')) {
            $pdf = Pdf::loadView('reports.ordem-servico-pdf', $data)
                ->setPaper('a4', 'portrait');
            $suffix = $tecnica ? '-tecnica' : '';

            return $pdf->download('OS-'.$data['numero'].$suffix.'.pdf');
        }

        return view('reports.ordem-servico', $data);
    }
}
