<?php

namespace App\Support\Erp\Reports\Tabular\Concerns;

use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AggregatesProductSales
{
    /**
     * Base comum: itens de vendas fechadas no período (com produto).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\VendaItem>
     */
    protected function productSalesBaseQuery(CarbonInterface $de, CarbonInterface $ate, ?Request $request = null)
    {
        $query = VendaItem::query()
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()])
            ->whereNotNull('venda_itens.product_id');

        if ($request && Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            ReportEmpresaScope::applyToQuery($query, $request, 'vendas.empresa_id');
        }

        return $query;
    }

    /**
     * Quantidade de produtos distintos no ranking (leve — só COUNT).
     */
    protected function productSalesRankedTotal(CarbonInterface $de, CarbonInterface $ate, ?Request $request = null): int
    {
        return (int) $this->productSalesBaseQuery($de, $ate, $request)
            ->distinct()
            ->count('venda_itens.product_id');
    }

    /**
     * Agrega vendas por produto.
     *
     * @param  'receita'|'qtd'  $orderMetric
     * @return Collection<int, object>
     */
    protected function productSalesAggregate(
        CarbonInterface $de,
        CarbonInterface $ate,
        ?Request $request = null,
        string $orderMetric = 'receita',
        string $orderDir = 'desc',
        int $limit = 5000,
    ): Collection {
        $query = $this->productSalesBaseQuery($de, $ate, $request)
            ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id');

        $vi = AbstractTabularReport::sqlTable('venda_itens');
        $orderExpr = $orderMetric === 'qtd'
            ? "SUM({$vi}.quantidade)"
            : "SUM({$vi}.total)";
        $dir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';
        $fetchLimit = max(1, min($limit, 5000));

        return $query
            ->groupBy(
                'venda_itens.product_id',
                'products.codigo',
                'products.descricao',
                'products.grupo',
                'products.preco_custo',
                'products.preco_compra',
            )
            ->orderByRaw("{$orderExpr} {$dir}")
            ->limit($fetchLimit)
            ->get([
                'venda_itens.product_id',
                'products.codigo',
                'products.descricao',
                'products.grupo',
                DB::raw("SUM({$vi}.quantidade) as qtd"),
                DB::raw("SUM({$vi}.total) as receita"),
                DB::raw(
                    "SUM({$vi}.quantidade * COALESCE("
                    .AbstractTabularReport::sqlTable('products').'.preco_custo, '
                    .AbstractTabularReport::sqlTable('products').'.preco_compra, 0)) as custo'
                ),
            ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapLucratividadeRows(Collection $rows, string $order = 'desc'): array
    {
        $mapped = $rows->map(function (object $row): array {
            $receita = (float) $row->receita;
            $custo = (float) $row->custo;
            $lucro = $receita - $custo;
            $margem = $receita > 0 ? ($lucro / $receita) * 100 : 0.0;

            return [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
                'grupo' => (string) ($row->grupo ?? ''),
                'qtd' => AbstractTabularReport::formatQuantity((float) $row->qtd),
                'receita' => AbstractTabularReport::formatMoney($receita),
                'custo' => AbstractTabularReport::formatMoney($custo),
                'lucro' => AbstractTabularReport::formatMoney($lucro),
                'margem' => AbstractTabularReport::formatPercent($margem),
                '_lucro' => $lucro,
            ];
        })->all();

        usort($mapped, function (array $a, array $b) use ($order): int {
            return $order === 'asc'
                ? $a['_lucro'] <=> $b['_lucro']
                : $b['_lucro'] <=> $a['_lucro'];
        });

        return array_map(static function (array $row): array {
            unset($row['_lucro']);

            return $row;
        }, $mapped);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapRankingRows(Collection $rows, string $order = 'desc'): array
    {
        $mapped = $rows->map(function (object $row): array {
            return [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
                'grupo' => (string) ($row->grupo ?? ''),
                'qtd' => AbstractTabularReport::formatQuantity((float) $row->qtd),
                'receita' => AbstractTabularReport::formatMoney((float) $row->receita),
                '_qtd' => (float) $row->qtd,
            ];
        })->all();

        usort($mapped, function (array $a, array $b) use ($order): int {
            return $order === 'asc'
                ? $a['_qtd'] <=> $b['_qtd']
                : $b['_qtd'] <=> $a['_qtd'];
        });

        return array_map(static function (array $row): array {
            unset($row['_qtd']);

            return $row;
        }, $mapped);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapCurvaAbc(Collection $rows): array
    {
        $totalReceita = (float) $rows->sum(fn (object $row): float => (float) $row->receita);

        if ($totalReceita <= 0) {
            return [];
        }

        $acumulado = 0.0;
        $result = [];

        foreach ($rows as $row) {
            $receita = (float) $row->receita;
            $pct = ($receita / $totalReceita) * 100;
            $acumulado += $pct;
            $classe = $acumulado <= 80 ? 'A' : ($acumulado <= 95 ? 'B' : 'C');

            $result[] = [
                'classe' => $classe,
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
                'grupo' => (string) ($row->grupo ?? ''),
                'qtd' => AbstractTabularReport::formatQuantity((float) $row->qtd),
                'receita' => AbstractTabularReport::formatMoney($receita),
                'pct' => AbstractTabularReport::formatPercent($pct),
                'pct_acum' => AbstractTabularReport::formatPercent($acumulado),
            ];
        }

        return $result;
    }
}
