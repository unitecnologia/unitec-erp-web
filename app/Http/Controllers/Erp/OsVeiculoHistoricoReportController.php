<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\OsVeiculo;
use App\Support\Erp\Os\OsVeiculoHistoricoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OsVeiculoHistoricoReportController
{
    public function __invoke(OsVeiculo $veiculo, OsVeiculoHistoricoService $historico): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = (int) session('erp_empresa_id', $user->empresa_id);
        abort_unless($empresaId > 0 && (int) $veiculo->empresa_id === $empresaId, 403);

        $empresa = Empresa::query()->find($empresaId);

        return view('reports.os-veiculo-historico', $historico->relatorio($empresaId, $veiculo, $empresa));
    }
}
