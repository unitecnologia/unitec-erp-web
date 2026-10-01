<?php

namespace App\Http\Controllers\Erp;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\Empresa;
use App\Support\Erp\Boleto\BoletoEnvioService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class BoletoPdfReportController
{
    public function __invoke(Request $request, Boleto $boleto): View|Response
    {
        abort_unless(
            ErpAccess::currentCan('boletos.create')
            || ErpAccess::currentCan('boletos.access')
            || ErpAccess::currentCan('contas_receber.access')
            || ErpAccess::currentCan('contas_receber.print'),
            403
        );

        $empresa = $boleto->empresa_id
            ? Empresa::query()->find($boleto->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            abort(404, 'Empresa não encontrada.');
        }

        if ($boleto->boleto_conta_api_id) {
            $contaApi = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
            if ($contaApi instanceof BoletoContaApi) {
                $empresa = $contaApi->asEmpresaOverlay($empresa);
            }
        }

        // Viewer HTML → diálogo de impressão do navegador (padrão NF-e).
        if ($request->boolean('print') || $request->boolean('auto')) {
            return view('reports.boleto-print', [
                'boleto' => $boleto,
                'pdfUrl' => route('erp.reports.boleto-pdf', ['boleto' => $boleto->id]),
                'autoPrint' => true,
            ]);
        }

        $pdf = app(BoletoEnvioService::class)->ensurePdf($boleto, $empresa);
        $path = $pdf['path'];
        $name = $pdf['name'];

        abort_unless(is_file($path), 404, 'PDF do boleto não encontrado.');

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                .'; filename="'.$name.'"',
        ]);
    }
}
