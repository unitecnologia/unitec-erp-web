<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela exclusiva de filtros do Relatório de Vendas Geral.
 * Separada do viewer tabular para abertura rápida e sem sidebar.
 */
class VendasGeralFiltrosController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(ErpAccess::currentCan('vendas.print'), 403);

        $report = new HistoricoVendasReport;
        $hoje = ErpTimezone::toLocal();

        $options = [];
        foreach ($report->filterFields() as $field) {
            if (($field['type'] ?? '') === 'select') {
                $options[(string) $field['key']] = $field['options'] ?? [];
            }
        }

        $empresa = ErpContext::currentEmpresa();
        $empresaLabel = $empresa
            ? trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)))
            : '—';

        $values = [
            'de' => (string) $request->query('de', $hoje->copy()->startOfMonth()->toDateString()),
            'ate' => (string) $request->query('ate', $hoje->toDateString()),
            'cliente_q' => (string) $request->query('cliente_q', ''),
            'vendedor' => (string) $request->query('vendedor', 'todos'),
            'cidade_q' => (string) $request->query('cidade_q', ''),
            'grupo' => (string) $request->query('grupo', 'todos'),
            'caixa' => (string) $request->query('caixa', 'todos'),
            'forma' => (string) $request->query('forma', 'todos'),
            'usuario' => (string) $request->query('usuario', 'todos'),
            'numero_q' => (string) $request->query('numero_q', ''),
            'origem' => (string) $request->query('origem', 'todos'),
            'status' => (string) $request->query('status', Venda::STATUS_FECHADO),
            'doc_tipo' => (string) $request->query('doc_tipo', 'todos'),
            'agrupar' => (string) $request->query('agrupar', 'nenhum'),
            'exibir_produtos' => $request->boolean('exibir_produtos'),
            'mostrar_resumo' => $request->boolean('mostrar_resumo'),
            'apenas_resumo' => $request->boolean('apenas_resumo'),
            'exibir_formas' => $request->boolean('exibir_formas'),
            'exibir_grafico' => $request->boolean('exibir_grafico'),
            'exibir_resumo_hora' => $request->boolean('exibir_resumo_hora'),
        ];

        return view('reports.vendas-geral-filtros', [
            'reportUrl' => ReportRegistry::route('historico-vendas'),
            'filtersUrl' => route('erp.reports.vendas-geral.filtros'),
            'clienteLookupUrl' => route('erp.reports.vendas-geral.clientes-lookup'),
            'cidadeLookupUrl' => route('erp.reports.vendas-geral.cidades-lookup'),
            'closeUrl' => url('/admin'),
            'empresaLabel' => $empresaLabel,
            'options' => $options,
            'values' => $values,
            'erro' => (string) $request->query('erro', ''),
        ]);
    }
}
