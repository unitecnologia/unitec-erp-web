<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricoVendasClienteReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-vendas-cliente';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE VENDAS POR CLIENTE';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'qtd' => 'QTD VENDAS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return ['cliente', 'qtd', 'total'];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter($this->periodFilterFields()));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: '',
        );
        $multi = $this->isMultiEmpresaScope($request);
        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');

        $query = Venda::query()
            ->leftJoin('people', 'people.id', '=', 'vendas.cliente_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()]);

        if ($hasEmpresa) {
            ReportEmpresaScope::applyToQuery($query, $request, 'vendas.empresa_id');
        }

        if ($multi && $hasEmpresa) {
            $query->groupBy('vendas.empresa_id', 'vendas.cliente_id', 'people.nome_razao')
                ->orderBy('vendas.empresa_id')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('vendas').'.total)'))
                ->select([
                    'vendas.empresa_id',
                    'people.nome_razao as cliente',
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);
        } else {
            $query->groupBy('vendas.cliente_id', 'people.nome_razao')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('vendas').'.total)'))
                ->select([
                    'people.nome_razao as cliente',
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);
        }

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $query->limit(5000)->get()->map(function ($row) use ($multi, $labels): array {
            $mapped = [
                'cliente' => (string) ($row->cliente ?: 'CONSUMIDOR'),
                'qtd' => static::formatQuantity((float) $row->qtd),
                'total' => static::formatMoney((float) $row->total),
            ];

            if ($multi) {
                $mapped['empresa'] = $labels[(int) ($row->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $mapped;
        })->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary(['PERÍODO: '.$this->periodLabel($de, $ate)], $request),
        );
    }
}
