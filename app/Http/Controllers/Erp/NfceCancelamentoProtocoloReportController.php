<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
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

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            abort_unless(
                (int) $venda->sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
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
