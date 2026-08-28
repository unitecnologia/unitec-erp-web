<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\Pdv\PdvCaixaResumoBobinaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PdvCaixaResumoReportController
{
    public function __invoke(Request $request, PdvCaixaSessao $sessao): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = session('erp_empresa_id', $user->empresa_id);

        if (filled($sessao->empresa_id)) {
            abort_unless(
                (int) $sessao->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        $empresa = $sessao->empresa_id
            ? Empresa::query()->find($sessao->empresa_id)
            : ($empresaId ? Empresa::query()->find($empresaId) : $user->empresa);

        $dinheiroInformado = (float) $request->query('dinheiro', 0);

        $built = app(PdvCaixaResumoBobinaBuilder::class)->buildFromSessao(
            $sessao,
            $empresa,
            $dinheiroInformado,
            $user->name ?? null,
        );

        return view('reports.pdv-resumo-caixa', [
            'lines' => $built['lines'],
            'autoPrint' => $request->boolean('auto'),
        ]);
    }
}
