<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendasProdutosClientesReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'vendas-produtos-clientes';
    }

    public function title(): string
    {
        return 'VENDAS DE PRODUTOS — CLIENTES';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'cliente' => 'CLIENTE',
            'codigo' => 'CÓDIGO',
            'descricao' => 'PRODUTO',
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

        $rows = VendaItem::query()
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->leftJoin('people', 'people.id', '=', 'vendas.cliente_id')
            ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('vendas.cliente_id', 'people.nome_razao', 'venda_itens.product_id', 'products.codigo', 'products.descricao')
            ->orderBy('people.nome_razao')
            ->orderByDesc(DB::raw('SUM(' . static::sqlTable('venda_itens') . '.total)'))
            ->limit(5000)
            ->get([
                'people.nome_razao as cliente',
                'products.codigo',
                'products.descricao',
                DB::raw('SUM(' . static::sqlTable('venda_itens') . '.quantidade) as qtd'),
                DB::raw('SUM(' . static::sqlTable('venda_itens') . '.total) as total'),
            ])
            ->map(fn ($row): array => [
                'cliente' => (string) ($row->cliente ?: 'CONSUMIDOR'),
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
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
