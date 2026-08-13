<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoricoVendasVendedorReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-vendas-vendedor';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE VENDAS POR VENDEDOR';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'vendedor' => 'VENDEDOR',
            'qtd' => 'QTD VENDAS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $rows = Venda::query()
            ->leftJoin('vendedores', 'vendedores.id', '=', 'vendas.vendedor_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('vendas.vendedor_id', 'vendedores.nome', 'vendas.vendedor_nome')
            ->orderByDesc(DB::raw('SUM(' . static::sqlTable('vendas') . '.total)'))
            ->limit(5000)
            ->get([
                DB::raw(
                    'COALESCE(NULLIF(' . static::sqlTable('vendedores') . '.nome, \'\'), NULLIF('
                    . static::sqlTable('vendas') . '.vendedor_nome, \'\'), \'SEM VENDEDOR\') as vendedor'
                ),
                DB::raw('COUNT(*) as qtd'),
                DB::raw('SUM(' . static::sqlTable('vendas') . '.total) as total'),
            ])
            ->map(fn ($row): array => [
                'vendedor' => (string) $row->vendedor,
                'qtd' => static::formatQuantity((float) $row->qtd),
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
