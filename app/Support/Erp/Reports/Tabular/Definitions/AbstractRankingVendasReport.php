<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\AggregatesProductSales;
use App\Support\Erp\Reports\Tabular\Concerns\LimitsRankingResults;
use Illuminate\Http\Request;

abstract class AbstractRankingVendasReport extends AbstractTabularReport
{
    use AggregatesProductSales;
    use LimitsRankingResults;

    abstract protected function sortOrder(): string;

    public function permission(): string
    {
        return 'produtos.print';
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
        $fields = $this->withEmpresaFilter($this->periodFilterFields());
        $fields[] = $this->resultLimitField();

        return $this->withColumnsField($fields);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $resultLimit = $this->resultLimitFromRequest($request);
        $rankedTotal = $this->productSalesRankedTotal($de, $ate, $request);

        // Ordena por quantidade no SQL e carrega só a quantidade pedida (após o ranking correto).
        $agg = $this->productSalesAggregate(
            $de,
            $ate,
            $request,
            'qtd',
            $this->sortOrder(),
            $resultLimit,
        );
        $rows = $this->applyResultLimit(
            $this->mapRankingRows($agg, $this->sortOrder()),
            $resultLimit,
        );

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'qtd_resultados' => (string) $resultLimit,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO: '.$this->periodLabel($de, $ate),
                'Exibindo '.count($rows).' de '.$rankedTotal.' produto(s) no ranking (limite '.$resultLimit.').',
            ], $request),
        );

        $built['meta'] = [
            'result_limit' => $resultLimit,
            'ranked_total' => $rankedTotal,
        ];

        return $built;
    }
}
