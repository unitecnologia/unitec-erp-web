<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NfceCupomReportController
{
    public function __invoke(Request $request, PdvVenda $venda, PdvNfceSimuladaService $service): View
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($venda->fiscal, 404);

        $venda->load(['itens', 'pagamentos', 'person', 'sessao', 'nfce']);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            abort_unless(
                (int) $venda->sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $operacao = (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR);

        return view('reports.nfce-cupom', $service->buildViewData(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) $user->name,
            operacao: $operacao,
            copias: max(1, min(3, (int) $request->query('copias', 1))),
            autoPrint: $request->boolean('auto'),
        ));
    }
}
