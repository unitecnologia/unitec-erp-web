<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendasFormaPagamentoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'vendas-forma-pagamento';
    }

    public function title(): string
    {
        return 'VENDAS POR FORMA DE PAGAMENTO';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'forma' => 'FORMA DE PAGAMENTO',
            'qtd' => 'QTD',
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
            ->where('status', Venda::STATUS_FECHADO)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('forma_pagamento')
            ->orderByDesc(DB::raw('SUM(total)'))
            ->get([
                'forma_pagamento as forma',
                DB::raw('COUNT(*) as qtd'),
                DB::raw('SUM(total) as total'),
            ])
            ->map(fn ($row): array => [
                'forma' => (string) ($row->forma ?: 'NÃO INFORMADA'),
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
