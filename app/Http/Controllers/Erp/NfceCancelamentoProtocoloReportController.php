<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Pdv\PdvNfceCancelamentoProtocoloService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NfceCancelamentoProtocoloReportController
{
    public function __invoke(
        Request $request,
        PdvVenda $venda,
        PdvNfceCancelamentoProtocoloService $service,
    ): View {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($venda->fiscal, 404);

        $venda->load(['sessao', 'nfce']);

        abort_unless(
            $venda->nfce !== null && filled($venda->nfce->protocolo_cancelamento),
            404,
        );

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        NfceEmpresaEscopo::abortSeVendaForaDaEmpresa($venda, $empresaId ? (int) $empresaId : null);

        if ($request->boolean('a4')) {
            return view(
                'reports.nfce-cancelamento-protocolo-a4',
                $service->buildA4ViewData(
                    venda: $venda,
                    empresa: $empresa,
                    usuario: (string) $user->name,
                    autoPrint: $request->boolean('auto'),
                    embed: $request->boolean('embed'),
                ),
            );
        }

        return view(
            'reports.nfce-cancelamento-protocolo',
            $service->buildViewData(
                venda: $venda,
                empresa: $empresa,
                usuario: (string) $user->name,
                autoPrint: $request->boolean('auto'),
            ),
        );
    }
}
