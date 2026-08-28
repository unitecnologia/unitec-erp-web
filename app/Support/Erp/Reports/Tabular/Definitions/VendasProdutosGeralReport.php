<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\AggregatesProductSales;
use Illuminate\Http\Request;

class VendasProdutosGeralReport extends AbstractTabularReport
{
    use AggregatesProductSales;

    public function slug(): string
    {
        return 'vendas-produtos-geral';
    }

    public function title(): string
    {
        return 'VENDAS POR PRODUTOS — GERAL';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'qtd' => 'QTD',
            'receita' => 'RECEITA',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'receita'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter($this->periodFilterFields()));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $rows = $this->mapRankingRows($this->productSalesAggregate($de, $ate, $request), 'desc');

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
