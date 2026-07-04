<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvPedidoReportData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PdvCupomReportController
{
    public function __invoke(Request $request, PdvVenda $venda): View
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $venda->load([
            'itens' => fn ($query) => $query->orderBy('id')->with('product'),
            'pagamentos',
            'person',
            'sessao.terminal',
            'vendedor',
        ]);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            abort_unless(
                (int) $venda->sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $autoPrint = $request->boolean('auto');
        $copias = max(1, min(3, (int) $request->query('copias', 1)));
        $terminal = PdvPedidoReportData::resolveTerminal($venda);

        if (PdvPedidoReportData::shouldUsePedidoA4($terminal)) {
            return view('reports.pdv-pedido-a4', PdvPedidoReportData::build(
                $venda,
                $empresa,
                $user->name,
                $autoPrint,
                $copias,
            ));
        }

        return view('reports.pdv-cupom', [
            'venda' => $venda,
            'empresa' => $empresa,
            'usuario' => $user->name,
            'autoPrint' => $autoPrint,
            'copias' => $copias,
            'printedAt' => now(),
        ]);
    }
}
