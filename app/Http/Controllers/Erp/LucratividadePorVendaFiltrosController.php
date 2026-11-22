<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\Tabular\Definitions\LucratividadePorVendaReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela compacta de filtros da Lucratividade por Venda (padrão Vendas Geral).
 * Sem pré-visualização vazia — só gera ao clicar em Gerar.
 */
class LucratividadePorVendaFiltrosController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(ErpAccess::currentCan('vendas.print'), 403);

        $report = new LucratividadePorVendaReport;
        // Sempre a data do dia (fuso local) — não reaproveita de/ate de acessos anteriores.
        $hojeStr = ErpTimezone::today();
        $dePadrao = $hojeStr;
        $atePadrao = $hojeStr;

        $vendedorOptions = ['todos' => 'Todos'];
        foreach ($report->filterFields() as $field) {
            if (($field['key'] ?? '') === 'vendedor' && ($field['type'] ?? '') === 'select') {
                $vendedorOptions = $field['options'] ?? $vendedorOptions;
            }
        }

        $empresa = ErpContext::currentEmpresa();
        $empresaLabel = $empresa
            ? trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)))
            : '—';

        $defaultCols = $report->defaultColumns();
        $requestedCols = $request->query('cols');
        if (is_array($requestedCols) && $requestedCols !== []) {
            $cols = array_values(array_intersect($defaultCols, array_map('strval', $requestedCols)));
            if ($cols === []) {
                $cols = $defaultCols;
            }
        } else {
            $cols = $defaultCols;
        }

        return view('reports.lucratividade-por-venda-filtros', [
            'reportUrl' => ReportRegistry::route('lucratividade-por-venda'),
            'filtersUrl' => route('erp.reports.lucratividade-por-venda.filtros'),
            'closeUrl' => url('/admin'),
            'empresaLabel' => $empresaLabel,
            'columnOptions' => $report->columns(),
            'selectedCols' => $cols,
            'vendedorOptions' => $vendedorOptions,
            'tipoOptions' => LucratividadePorVendaReport::tipoOptions(),
            'values' => [
                'de' => $dePadrao,
                'ate' => $atePadrao,
                'cliente_q' => (string) $request->query('cliente_q', ''),
                'vendedor' => (string) $request->query('vendedor', 'todos'),
                'tipo' => LucratividadePorVendaReport::normalizeTipo(
                    (string) $request->query('tipo', LucratividadePorVendaReport::TIPO_DETALHADO)
                ),
            ],
            'erro' => (string) $request->query('erro', ''),
        ]);
    }
}
