<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ProductGrade;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class EstoqueGradeReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'estoque-grade';
    }

    public function title(): string
    {
        return 'ESTOQUE — GRADE';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'produto' => 'PRODUTO',
            'descricao' => 'GRADE',
            'tamanho' => 'TAMANHO',
            'qtd' => 'QTD',
            'preco' => 'PREÇO',
            'preco_atacado' => 'ATACADO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'preco', 'preco_atacado'];
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

        $rows = ProductGrade::query()
            ->with('product')
            ->when($q !== '', function ($query) use ($q): void {
                $query->whereHas('product', function ($product) use ($q): void {
                    $like = '%' . $q . '%';
                    $product->where('codigo', 'like', $like)->orWhere('descricao', 'like', $like);
                });
            })
            ->orderBy('product_id')
            ->limit(5000)
            ->get()
            ->map(fn (ProductGrade $item): array => [
                'produto' => trim(($item->product?->codigo ?? '') . ' — ' . ($item->product?->descricao ?? ''), ' —'),
                'descricao' => (string) ($item->descricao ?? ''),
                'tamanho' => (string) ($item->tamanho ?? ''),
                'qtd' => static::formatQuantity((float) $item->qtd),
                'preco' => static::formatMoney((float) $item->preco),
                'preco_atacado' => static::formatMoney((float) $item->preco_atacado),
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
