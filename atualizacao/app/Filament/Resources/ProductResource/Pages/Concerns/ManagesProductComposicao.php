<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\ProductPriceCalculator;
use Filament\Notifications\Notification;

trait ManagesProductComposicao
{
    /** @var array<int, array<string, mixed>> */
    public array $compositionRows = [];

    public ?int $selectedCompositionIndex = null;

    public string $compositionProductCodigo = '';

    public string $compositionQuantidade = '1';

    public string $compositionPreco = '0,00';

    protected function loadProductCompositions(?Product $product = null): void
    {
        if (! $product) {
            $this->compositionRows = [];
            $this->selectedCompositionIndex = null;

            return;
        }

        $this->compositionRows = $product->compositions()
            ->with('componentProduct:id,codigo,descricao,preco_venda')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductComposition $item): array => [
                'id' => $item->id,
                'component_product_id' => $item->component_product_id,
                'codigo' => $item->componentProduct?->codigo,
                'descricao' => $item->componentProduct?->descricao,
                'quantidade' => $this->formatBrDecimal($item->quantidade, 3),
                'preco' => $this->formatBrDecimal($item->preco, 2),
                'total' => $this->formatBrDecimal($item->total, 2),
            ])
            ->values()
            ->all();
    }

    public function selectCompositionRow(int $index): void
    {
        if (! isset($this->compositionRows[$index])) {
            return;
        }

        $this->selectedCompositionIndex = $index;
        $row = $this->compositionRows[$index];
        $this->compositionProductCodigo = (string) ($row['codigo'] ?? '');
        $this->compositionQuantidade = (string) ($row['quantidade'] ?? '1');
        $this->compositionPreco = (string) ($row['preco'] ?? '0,00');
    }

    public function addCompositionItem(): void
    {
        $codigo = trim($this->compositionProductCodigo);
        $component = Product::query()->where('codigo', $codigo)->first();

        if (! $component) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Informe o código de um produto válido.')
                ->warning()
                ->send();

            return;
        }

        if ($this->isEditingProduct() && (int) $component->getKey() === (int) $this->record?->getKey()) {
            Notification::make()
                ->title('O produto não pode compor a si mesmo.')
                ->warning()
                ->send();

            return;
        }

        $quantidade = $this->parseBrDecimal($this->compositionQuantidade ?: '1', 3);
        $preco = $this->parseBrDecimal(
            $this->compositionPreco !== '0,00' && filled($this->compositionPreco)
                ? $this->compositionPreco
                : $component->preco_venda,
            2,
        );
        $total = round($quantidade * $preco, 2);

        $payload = [
            'id' => null,
            'component_product_id' => $component->getKey(),
            'codigo' => $component->codigo,
            'descricao' => $component->descricao,
            'quantidade' => $this->formatBrDecimal($quantidade, 3),
            'preco' => $this->formatBrDecimal($preco, 2),
            'total' => $this->formatBrDecimal($total, 2),
        ];

        if ($this->selectedCompositionIndex !== null && isset($this->compositionRows[$this->selectedCompositionIndex])) {
            $payload['id'] = $this->compositionRows[$this->selectedCompositionIndex]['id'] ?? null;
            $this->compositionRows[$this->selectedCompositionIndex] = $payload;
        } else {
            $existingIndex = collect($this->compositionRows)
                ->search(fn (array $row): bool => (int) ($row['component_product_id'] ?? 0) === (int) $component->getKey());

            if ($existingIndex !== false) {
                $payload['id'] = $this->compositionRows[$existingIndex]['id'] ?? null;
                $this->compositionRows[$existingIndex] = $payload;
                $this->selectedCompositionIndex = $existingIndex;
            } else {
                $this->compositionRows[] = $payload;
                $this->selectedCompositionIndex = count($this->compositionRows) - 1;
            }
        }

        $this->compositionProductCodigo = '';
        $this->compositionQuantidade = '1';
        $this->compositionPreco = '0,00';

        $this->applyCompositionCostToForm();

        Notification::make()
            ->title('Item de composição atualizado.')
            ->body('Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function deleteCompositionItem(): void
    {
        if ($this->selectedCompositionIndex === null || ! isset($this->compositionRows[$this->selectedCompositionIndex])) {
            Notification::make()
                ->title('Selecione um item da composição.')
                ->warning()
                ->send();

            return;
        }

        unset($this->compositionRows[$this->selectedCompositionIndex]);
        $this->compositionRows = array_values($this->compositionRows);
        $this->selectedCompositionIndex = null;
        $this->compositionProductCodigo = '';
        $this->compositionQuantidade = '1';
        $this->compositionPreco = '0,00';

        $this->applyCompositionCostToForm();
    }

    public function compositionRowsTotal(): float
    {
        return collect($this->compositionRows)
            ->sum(fn (array $row): float => $this->parseBrDecimal($row['total'] ?? 0, 2));
    }

    protected function applyCompositionCostToForm(): void
    {
        if (! ($this->data['is_composicao'] ?? false)) {
            return;
        }

        $total = $this->compositionRowsTotal();

        if ($total <= 0) {
            return;
        }

        $this->data['preco_compra'] = $this->formatBrDecimal($total, 2);
        $this->data = $this->formatProductFormDataForDisplay(
            ProductPriceCalculator::recalculateFromCompra(
                array_merge($this->data, ['preco_compra' => $total]),
            ),
        );
        $this->form->fill($this->data);
        $this->dispatch('erp-masks-refresh');
    }

    protected function syncProductCompositions(Product $product): void
    {
        if (! ($product->is_composicao ?? false)) {
            $product->compositions()->delete();

            return;
        }

        $ids = [];

        foreach ($this->compositionRows as $row) {
            $componentId = (int) ($row['component_product_id'] ?? 0);

            if ($componentId <= 0 || $componentId === (int) $product->getKey()) {
                continue;
            }

            $quantidade = $this->parseBrDecimal($row['quantidade'] ?? 0, 3);
            $preco = $this->parseBrDecimal($row['preco'] ?? 0, 2);

            $attributes = [
                'component_product_id' => $componentId,
                'quantidade' => $quantidade,
                'preco' => $preco,
                'total' => round($quantidade * $preco, 2),
            ];

            if (filled($row['id'] ?? null)) {
                ProductComposition::query()->whereKey($row['id'])->update($attributes);
                $ids[] = (int) $row['id'];
            } else {
                $created = $product->compositions()->create($attributes);
                $ids[] = $created->id;
            }
        }

        $product->compositions()->whereNotIn('id', $ids)->delete();

        if ($product->is_composicao) {
            $total = (float) $product->compositions()->sum('total');

            if ($total > 0) {
                $product->update([
                    'preco_compra' => $total,
                    'preco_custo' => $total,
                    'preco_venda' => round($total + ($total * (float) $product->pct_lucro / 100), 2),
                ]);
            }
        }
    }
}
