<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class PrecoAlteradoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'preco-alterado';
    }

    public function title(): string
    {
        return 'PRODUTOS COM PREÇO ALTERADO';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'data' => 'DATA',
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'preco_anterior' => 'PREÇO ANTERIOR',
            'preco_atual' => 'PREÇO ATUAL',
            'usuario' => 'USUÁRIO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['preco_anterior', 'preco_atual'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $historico = ProductPriceHistory::query()
            ->with('product')
            ->whereBetween('registrado_em', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('registrado_em')
            ->limit(5000)
            ->get();

        if ($historico->isNotEmpty()) {
            $rows = $historico->map(fn (ProductPriceHistory $item): array => [
                'data' => static::formatDate($item->registrado_em),
                'codigo' => (string) ($item->product?->codigo ?? ''),
                'descricao' => (string) ($item->product?->descricao ?? ''),
                'preco_anterior' => static::formatMoney((float) $item->ultimo_preco),
                'preco_atual' => static::formatMoney((float) ($item->product?->preco_venda ?? 0)),
                'usuario' => (string) ($item->usuario ?? ''),
            ])->all();
        } else {
            // Fallback: produtos com preco_venda_anterior preenchido
            $rows = Product::query()
                ->whereNotNull('preco_venda_anterior')
                ->where('preco_venda_anterior', '>', 0)
                ->whereColumn('preco_venda', '!=', 'preco_venda_anterior')
                ->orderBy('descricao')
                ->limit(5000)
                ->get()
                ->map(fn (Product $product): array => [
                    'data' => '',
                    'codigo' => (string) ($product->codigo ?? ''),
                    'descricao' => (string) ($product->descricao ?? ''),
                    'preco_anterior' => static::formatMoney((float) $product->preco_venda_anterior),
                    'preco_atual' => static::formatMoney((float) $product->preco_venda),
                    'usuario' => '',
                ])
                ->all();
        }

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
        );
    }
}
