<?php

namespace App\Http\Controllers\Erp;

use App\Models\CaixaLivroMovimento;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\CaixaLivroFechamentoReportData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Relatório A4 do fechamento do Livro Caixa (somente leitura).
 */
final class CaixaLivroFechamentoReportController
{
    public function __invoke(Request $request, CaixaLivroMovimento $movimento): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? $user->empresa_id ?? 0);
        abort_unless($empresaId > 0, 403);
        abort_unless((int) $movimento->empresa_id === $empresaId, 403);
        abort_unless($movimento->isFechado(), 422, 'Movimento ainda aberto — impressão de fechamento indisponível.');

        $flags = [
            'resumo' => $request->boolean('resumo', true),
            'formas' => $request->boolean('formas', true),
            'vendas' => $request->boolean('vendas', false),
            'clientes' => $request->boolean('clientes', false),
            'entradas_saidas' => $request->boolean('entradas_saidas', false),
            'transferencias' => $request->boolean('transferencias', false),
            'operadores' => $request->boolean('operadores', true),
        ];

        try {
            $data = app(CaixaLivroFechamentoReportData::class)->build($movimento, $flags);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return view('reports.caixa-livro-fechamento-a4', array_merge($data, [
            'autoPrint' => $request->boolean('auto'),
            'embed' => $request->boolean('embed'),
        ]));
    }
}
