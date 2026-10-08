<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
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

        $venda->load(['itens', 'pagamentos', 'person', 'sessao', 'nfce', 'venda', 'vendedor']);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        NfceEmpresaEscopo::abortSeVendaForaDaEmpresa($venda, $empresaId ? (int) $empresaId : null);
        NfceImpressaoFiscal::abortSeBloqueada($venda);

        $operacao = (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR);

        return view('reports.nfce-cupom', array_merge(
            $service->buildViewData(
                venda: $venda,
                empresa: $empresa,
                usuario: (string) $user->name,
                operacao: $operacao,
                copias: max(1, min(3, (int) $request->query('copias', 1))),
                autoPrint: $request->boolean('auto'),
            ),
            [
                'embed' => $request->boolean('embed'),
            ],
        ));
    }
}
