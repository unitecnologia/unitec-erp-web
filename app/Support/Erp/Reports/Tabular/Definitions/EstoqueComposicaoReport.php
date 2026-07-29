<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ProductComposition;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class EstoqueComposicaoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'estoque-composicao';
    }

    public function title(): string
    {
        return 'ESTOQUE — COMPOSIÇÃO';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'produto' => 'PRODUTO',
            'componente' => 'COMPONENTE',
            'qtd' => 'QTD',
            'preco' => 'PREÇO',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'preco', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ['key' => 'q', 'label' => 'Produto', 'type' => 'text'],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $q = trim((string) $request->query('q', ''));

        $rows = ProductComposition::query()
            ->with(['product', 'componentProduct'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->whereHas('product', function ($product) use ($q): void {
                    $like = '%' . $q . '%';
                    $product->where('codigo', 'like', $like)->orWhere('descricao', 'like', $like);
                });
            })
            ->orderBy('product_id')
            ->limit(5000)
            ->get()
            ->map(fn (ProductComposition $item): array => [
                'produto' => trim(($item->product?->codigo ?? '') . ' — ' . ($item->product?->descricao ?? ''), ' —'),
                'componente' => trim(($item->componentProduct?->codigo ?? '') . ' — ' . ($item->componentProduct?->descricao ?? ''), ' —'),
                'qtd' => static::formatQuantity((float) $item->quantidade),
                'preco' => static::formatMoney((float) $item->preco),
                'total' => static::formatMoney((float) $item->total),
            ])
            ->all();

        $summary = [];
        if ($q !== '') {
            $summary[] = 'PRODUTO: ' . mb_strtoupper($q, 'UTF-8');
        }

        return $this->result(
            ['q' => $q, 'cols' => $columns],
            $columns,
            $rows,
            $summary,
        );
    }
}
