<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Compra;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricoComprasFornecedorReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-compras-fornecedor';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE COMPRAS POR FORNECEDOR';
    }

    public function permission(): string
    {
        return 'compras.print';
    }

    public function columns(): array
    {
        return [
            'empresa' => 'EMPRESA',
            'fornecedor' => 'FORNECEDOR',
            'qtd_compras' => 'QTD COMPRAS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return ['fornecedor', 'qtd_compras', 'total'];
    }

    public function numericColumns(): array
    {
        return ['qtd_compras', 'total'];
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
        $hasEmpresa = Schema::hasColumn((new Compra)->getTable(), 'empresa_id');

        $query = Compra::query()
            ->leftJoin('people', 'people.id', '=', 'compras.fornecedor_id')
            ->where('compras.status', '!=', Compra::STATUS_CANCELADA)
            ->whereBetween('compras.data_entrada', [$de->toDateString(), $ate->toDateString()]);

        if ($hasEmpresa) {
            ReportEmpresaScope::applyToQuery($query, $request, 'compras.empresa_id');
        }

        if ($multi && $hasEmpresa) {
            $query->groupBy('compras.empresa_id', 'compras.fornecedor_id', 'people.nome_razao')
                ->orderBy('compras.empresa_id')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('compras').'.total)'))
                ->select([
                    'compras.empresa_id',
                    'people.nome_razao as fornecedor',
                    DB::raw('COUNT(*) as qtd_compras'),
                    DB::raw('SUM('.static::sqlTable('compras').'.total) as total'),
                ]);
        } else {
            $query->groupBy('compras.fornecedor_id', 'people.nome_razao')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('compras').'.total)'))
                ->select([
                    'people.nome_razao as fornecedor',
                    DB::raw('COUNT(*) as qtd_compras'),
                    DB::raw('SUM('.static::sqlTable('compras').'.total) as total'),
                ]);
        }

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $query->limit(5000)->get()->map(function ($row) use ($multi, $labels): array {
            $mapped = [
                'fornecedor' => (string) ($row->fornecedor ?: 'SEM FORNECEDOR'),
                'qtd_compras' => static::formatQuantity((float) $row->qtd_compras),
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
