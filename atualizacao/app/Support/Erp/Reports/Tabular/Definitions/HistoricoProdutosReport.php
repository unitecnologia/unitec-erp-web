<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\AjusteEstoque;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class HistoricoProdutosReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-produtos';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE PRODUTOS';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'data' => 'DATA',
            'tipo' => 'TIPO',
            'documento' => 'DOCUMENTO',
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'qtd' => 'QTD',
            'valor' => 'VALOR',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'valor'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            ['key' => 'q', 'label' => 'Produto (código/descrição)', 'type' => 'text'],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $q = trim((string) $request->query('q', ''));
        $rows = [];

        $vendas = VendaItem::query()
            ->with(['product', 'venda'])
            ->whereHas('venda', fn ($query) => $query
                ->where('status', Venda::STATUS_FECHADO)
                ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]))
            ->when($q !== '', function ($query) use ($q): void {
                $query->whereHas('product', function ($product) use ($q): void {
                    $like = '%' . $q . '%';
                    $product->where('codigo', 'like', $like)->orWhere('descricao', 'like', $like);
                });
            })
            ->limit(2000)
            ->get();

        foreach ($vendas as $item) {
            $rows[] = [
                'data' => static::formatDate($item->venda?->data),
                'tipo' => 'VENDA',
                'documento' => (string) ($item->venda?->numero ?? ''),
                'codigo' => (string) ($item->product?->codigo ?? ''),
                'descricao' => (string) ($item->product?->descricao ?? ''),
                'qtd' => static::formatQuantity((float) $item->quantidade),
                'valor' => static::formatMoney((float) $item->total),
                '_sort' => optional($item->venda?->data)?->format('Y-m-d') . '-V-' . $item->id,
            ];
        }

        $compras = CompraItem::query()
            ->with(['product', 'compra'])
            ->whereHas('compra', fn ($query) => $query
                ->where('status', '!=', Compra::STATUS_CANCELADA)
                ->whereBetween('data_entrada', [$de->toDateString(), $ate->toDateString()]))
            ->when($q !== '', function ($query) use ($q): void {
                $query->whereHas('product', function ($product) use ($q): void {
                    $like = '%' . $q . '%';
                    $product->where('codigo', 'like', $like)->orWhere('descricao', 'like', $like);
                });
            })
            ->limit(2000)
            ->get();

        foreach ($compras as $item) {
            $rows[] = [
                'data' => static::formatDate($item->compra?->data_entrada),
                'tipo' => 'COMPRA',
                'documento' => (string) ($item->compra?->numero ?? ''),
                'codigo' => (string) ($item->product?->codigo ?? ''),
                'descricao' => (string) ($item->product?->descricao ?? ''),
                'qtd' => static::formatQuantity((float) $item->quantidade),
                'valor' => static::formatMoney((float) $item->total),
                '_sort' => optional($item->compra?->data_entrada)?->format('Y-m-d') . '-C-' . $item->id,
            ];
        }

        $ajustes = AjusteEstoque::query()
            ->with('product')
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->when($q !== '', function ($query) use ($q): void {
                $query->whereHas('product', function ($product) use ($q): void {
                    $like = '%' . $q . '%';
                    $product->where('codigo', 'like', $like)->orWhere('descricao', 'like', $like);
                });
            })
            ->limit(2000)
            ->get();

        foreach ($ajustes as $ajuste) {
            $rows[] = [
                'data' => static::formatDate($ajuste->data),
                'tipo' => 'AJUSTE',
                'documento' => (string) $ajuste->id,
                'codigo' => (string) ($ajuste->product?->codigo ?? ''),
                'descricao' => (string) ($ajuste->product?->descricao ?? ''),
                'qtd' => static::formatQuantity((float) $ajuste->qtd_ajust),
                'valor' => '',
                '_sort' => optional($ajuste->data)?->format('Y-m-d') . '-A-' . $ajuste->id,
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $a['_sort'], (string) $b['_sort']));
        $rows = array_map(static function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, $rows);

        $summary = ['PERÍODO: ' . $this->periodLabel($de, $ate)];
        if ($q !== '') {
            $summary[] = 'PRODUTO: ' . mb_strtoupper($q, 'UTF-8');
        }

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'q' => $q, 'cols' => $columns],
            $columns,
            $rows,
            $summary,
        );
    }
}
