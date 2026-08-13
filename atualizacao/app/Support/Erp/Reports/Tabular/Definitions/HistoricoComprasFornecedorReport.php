<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Compra;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'fornecedor' => 'FORNECEDOR',
            'qtd_compras' => 'QTD COMPRAS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd_compras', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $rows = Compra::query()
            ->leftJoin('people', 'people.id', '=', 'compras.fornecedor_id')
            ->where('compras.status', '!=', Compra::STATUS_CANCELADA)
            ->whereBetween('compras.data_entrada', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('compras.fornecedor_id', 'people.nome_razao')
            ->orderByDesc(DB::raw('SUM(' . static::sqlTable('compras') . '.total)'))
            ->limit(5000)
            ->get([
                'people.nome_razao as fornecedor',
                DB::raw('COUNT(*) as qtd_compras'),
                DB::raw('SUM(' . static::sqlTable('compras') . '.total) as total'),
            ])
            ->map(fn ($row): array => [
                'fornecedor' => (string) ($row->fornecedor ?: 'SEM FORNECEDOR'),
                'qtd_compras' => static::formatQuantity((float) $row->qtd_compras),
                'total' => static::formatMoney((float) $row->total),
            ])
            ->all();

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
        );
    }
}
