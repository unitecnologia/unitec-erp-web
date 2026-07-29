<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\AggregatesProductSales;
use Illuminate\Http\Request;

class CurvaAbcReport extends AbstractTabularReport
{
    use AggregatesProductSales;

    public function slug(): string
    {
        return 'curva-abc';
    }

    public function title(): string
    {
        return 'CURVA ABC DE PRODUTOS';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'classe' => 'CLASSE',
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'qtd' => 'QTD',
            'receita' => 'RECEITA',
            'pct' => '%',
            'pct_acum' => '% ACUM.',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'receita', 'pct', 'pct_acum'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $rows = $this->mapCurvaAbc($this->productSalesAggregate($de, $ate));

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
        );
    }
}
