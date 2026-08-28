<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\Pdv\PdvMovimentoCaixaBobinaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PdvMovimentoCaixaReportController
{
    public function __invoke(Request $request, PdvCaixaMovimento $movimento): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $tipo = mb_strtolower(trim((string) $movimento->tipo), 'UTF-8');
        abort_unless(in_array($tipo, ['sangria', 'suprimento'], true), 404);

        $movimento->load(['sessao.user', 'sessao.terminal', 'sessao.empresa']);

        $sessao = $movimento->sessao;
        abort_unless($sessao, 404);

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

        $built = app(PdvMovimentoCaixaBobinaBuilder::class)->build(
            $movimento,
            $empresa,
            $user->name ?? null,
        );

        return view('reports.pdv-movimento-caixa', [
            'tipo' => $built['tipo'],
            'lines' => $built['lines'],
            'autoPrint' => $request->boolean('auto'),
        ]);
    }
}
