<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Support\Erp\Os\OsProdutoBusca;
use App\Support\Erp\ProductCompositionCycleGuard;
use App\Support\Erp\ProductPriceCalculator;
use Filament\Notifications\Notification;

trait ManagesProductComposicao
{
    /** @var array<int, array<string, mixed>> */
    public array $compositionRows = [];

    public ?int $selectedCompositionIndex = null;

    /** Texto do campo de busca (código ou nome). */
    public string $compositionProductSearch = '';

    public ?int $compositionPendingProductId = null;

    public string $compositionQuantidade = '1,000';

    public string $compositionPreco = '0,00';

    public bool $productCompositionsHydrated = false;

    /** @var array<int, array<string, mixed>> */
    public array $compositionProductSuggestions = [];

    public bool $compositionSuggestionsOpen = false;

    public ?int $compositionSuggestionIndex = null;

    /** Evita que updatedCompositionProductSearch limpe a seleção programática. */
    protected bool $compositionMuteSearchHook = false;

    protected function ensureProductCompositionsLoaded(): void
    {
        if ($this->productCompositionsHydrated) {
            return;
        }

        $product = ($this->record ?? null) instanceof Product ? $this->record : null;
        $this->loadProductCompositions($product);
    }

    protected function loadProductCompositions(?Product $product = null): void
    {
        $this->productCompositionsHydrated = true;

        if (! $product) {
            $this->compositionRows = [];
            $this->clearCompositionEditor(resetSelection: true);

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
                'is_adicional' => (bool) ($item->is_adicional ?? false),
                'quantidade' => $this->formatBrDecimal($item->quantidade, 3),
                'preco' => $this->formatBrDecimal($item->preco, 2),
                'total' => $this->formatBrDecimal($item->total, 2),
            ])
            ->values()
            ->all();

        $this->clearCompositionEditor(resetSelection: true);
    }

    public function selectCompositionRow(int $index): void
    {
        if (! isset($this->compositionRows[$index])) {
            return;
        }

        $this->selectedCompositionIndex = $index;
        $row = $this->compositionRows[$index];

        $this->withoutCompositionSearchHook(function () use ($row): void {
            $this->compositionPendingProductId = (int) ($row['component_product_id'] ?? 0) ?: null;
            $this->compositionProductSearch = trim((string) ($row['codigo'] ?? ''));
            $this->compositionQuantidade = (string) ($row['quantidade'] ?? '1,000');
            $this->compositionPreco = (string) ($row['preco'] ?? '0,00');
        });

        $this->closeCompositionSuggestions();
    }

    public function clearCompositionSelection(): void
    {
        $this->clearCompositionEditor(resetSelection: true);
    }

    public function updatedCompositionProductSearch(): void
    {
        if ($this->compositionMuteSearchHook) {
            return;
        }

        $term = mb_strtoupper(trim($this->compositionProductSearch), 'UTF-8');

        if ($term !== $this->compositionProductSearch) {
            $this->compositionProductSearch = $term;
        }

        $this->compositionPendingProductId = null;
        $this->refreshCompositionProductSuggestions();
    }

    public function openCompositionSuggestions(): void
    {
        $this->refreshCompositionProductSuggestions();
    }

    public function closeCompositionSuggestions(): void
    {
        $this->compositionProductSuggestions = [];
        $this->compositionSuggestionsOpen = false;
        $this->compositionSuggestionIndex = null;
    }

    public function selectCompositionSuggestion(int $productId): void
    {
        $product = Product::query()
            ->whereKey($productId)
            ->first(['id', 'codigo', 'descricao', 'preco_venda']);

        if (! $product) {
            return;
        }

        $this->withoutCompositionSearchHook(function () use ($product): void {
            $this->compositionPendingProductId = (int) $product->id;
            $this->compositionProductSearch = (string) $product->codigo;
            $this->compositionPreco = $this->formatBrDecimal($product->preco_venda, 2);
        });

        $this->closeCompositionSuggestions();
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-composicao-focus-qtd');
    }

    /**
     * @param  callable(): void  $callback
     */
    protected function withoutCompositionSearchHook(callable $callback): void
    {
        $this->compositionMuteSearchHook = true;

        try {
            $callback();
        } finally {
            $this->compositionMuteSearchHook = false;
        }
    }

    /**
     * Enter no campo de busca: só seleciona o produto (não inclui na grade).
     */
    public function confirmCompositionSuggestionFromKeyboard(): void
    {
        if ($this->compositionSuggestionsOpen && $this->compositionProductSuggestions !== []) {
            $index = $this->compositionSuggestionIndex ?? 0;

            if (isset($this->compositionProductSuggestions[$index])) {
                $this->selectCompositionSuggestion((int) $this->compositionProductSuggestions[$index]['id']);

                return;
            }
        }

        $component = $this->resolveCompositionComponent();

        if ($component) {
            $this->selectCompositionSuggestion((int) $component->getKey());
        }
    }

    /**
     * Enter no Preço: inclui/atualiza e devolve o foco à pesquisa.
     */
    public function confirmCompositionFromPreco(?string $preco = null): void
    {
        if ($preco !== null && trim($preco) !== '') {
            $this->compositionPreco = $preco;
        }

        if ($this->selectedCompositionIndex !== null) {
            $this->updateCompositionItem();
        } else {
            $this->addCompositionItem();
        }
    }

    public function addCompositionItem(): void
    {
        $this->commitCompositionItem(updateSelected: false);
    }

    public function updateCompositionItem(): void
    {
        if ($this->selectedCompositionIndex === null || ! isset($this->compositionRows[$this->selectedCompositionIndex])) {
            Notification::make()
                ->title('Selecione um item da composição para atualizar.')
                ->warning()
                ->send();

            return;
        }

        $this->commitCompositionItem(updateSelected: true);
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
        $this->clearCompositionEditor(resetSelection: true);
        $this->applyCompositionCostToForm();
    }

    public function compositionRowsTotal(): float
    {
        return collect($this->compositionRows)
            ->sum(fn (array $row): float => $this->parseBrDecimal($row['total'] ?? 0, 2));
    }

    protected function commitCompositionItem(bool $updateSelected): void
    {
        $component = $this->resolveCompositionComponent();

        if (! $component) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Informe o código ou o nome de um produto válido.')
                ->warning()
                ->send();

            return;
        }

        $kitId = $this->isEditingProduct() ? (int) ($this->record?->getKey() ?? 0) : 0;

        if ($kitId > 0 && ProductCompositionCycleGuard::wouldCreateCycle($kitId, (int) $component->getKey())) {
            Notification::make()
                ->title('Composição circular não permitida.')
                ->body('Este produto já depende do item atual (direta ou indiretamente).')
                ->warning()
                ->send();

            return;
        }

        $quantidade = $this->parseBrDecimal($this->compositionQuantidade ?: '1', 3);

        if ($quantidade <= 0) {
            Notification::make()
                ->title('Quantidade inválida.')
                ->body('Informe uma quantidade decimal maior que zero.')
                ->warning()
                ->send();

            return;
        }

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
            'is_adicional' => false,
            'quantidade' => $this->formatBrDecimal($quantidade, 3),
            'preco' => $this->formatBrDecimal($preco, 2),
            'total' => $this->formatBrDecimal($total, 2),
        ];

        $existingIndex = collect($this->compositionRows)
            ->search(fn (array $row): bool => (int) ($row['component_product_id'] ?? 0) === (int) $component->getKey());

        if ($updateSelected) {
            $selected = (int) $this->selectedCompositionIndex;

            if ($existingIndex !== false && (int) $existingIndex !== $selected) {
                Notification::make()
                    ->title('Componente já está na composição.')
                    ->body('Selecione a linha existente para alterar quantidade/preço.')
                    ->warning()
                    ->send();

                return;
            }

            $payload['id'] = $this->compositionRows[$selected]['id'] ?? null;
            $payload['is_adicional'] = (bool) ($this->compositionRows[$selected]['is_adicional'] ?? false);
            $this->compositionRows[$selected] = $payload;
        } elseif ($existingIndex !== false) {
            $payload['id'] = $this->compositionRows[$existingIndex]['id'] ?? null;
            $payload['is_adicional'] = (bool) ($this->compositionRows[$existingIndex]['is_adicional'] ?? false);
            $this->compositionRows[$existingIndex] = $payload;
        } else {
            $this->compositionRows[] = $payload;
        }

        $this->clearCompositionEditor(resetSelection: true);
        $this->applyCompositionCostToForm();
        $this->dispatch('erp-composicao-focus-search');

        Notification::make()
            ->title($updateSelected ? 'Item de composição atualizado.' : 'Item incluído na composição.')
            ->body('Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    protected function resolveCompositionComponent(): ?Product
    {
        if ($this->compositionPendingProductId) {
            $byId = Product::query()->whereKey($this->compositionPendingProductId)->first();

            if ($byId) {
                return $byId;
            }
        }

        $term = trim($this->compositionProductSearch);

        if ($term === '') {
            return null;
        }

        $byCodigo = Product::query()->where('codigo', $term)->first();

        if ($byCodigo) {
            return $byCodigo;
        }

        $this->refreshCompositionProductSuggestions();

        if (count($this->compositionProductSuggestions) === 1) {
            return Product::query()->whereKey($this->compositionProductSuggestions[0]['id'])->first();
        }

        return null;
    }

    protected function refreshCompositionProductSuggestions(): void
    {
        $term = trim($this->compositionProductSearch);

        if (mb_strlen($term) < 1) {
            $this->closeCompositionSuggestions();

            return;
        }

        $excludeId = $this->isEditingProduct() ? (int) ($this->record?->getKey() ?? 0) : 0;

        $query = Product::query()
            ->where('ativo', true)
            ->when($excludeId > 0, fn ($q) => $q->where('id', '!=', $excludeId));

        OsProdutoBusca::aplicar($query, $term);

        $this->compositionProductSuggestions = $query
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'preco_venda'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) $product->codigo,
                'descricao' => (string) $product->descricao,
                'preco' => number_format((float) $product->preco_venda, 2, ',', '.'),
            ])
            ->values()
            ->all();

        $this->compositionSuggestionsOpen = $this->compositionProductSuggestions !== [];
        $this->compositionSuggestionIndex = $this->compositionProductSuggestions === [] ? null : 0;
    }

    /**
     * @param  bool  $resetSelection  Se true, limpa também a linha selecionada na grade.
     */
    protected function clearCompositionEditor(bool $resetSelection = false): void
    {
        if ($resetSelection) {
            $this->selectedCompositionIndex = null;
        }

        $this->withoutCompositionSearchHook(function (): void {
            $this->compositionProductSearch = '';
            $this->compositionPendingProductId = null;
            $this->compositionQuantidade = '1,000';
            $this->compositionPreco = '0,00';
        });

        $this->closeCompositionSuggestions();
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

        if (! $this->productCompositionsHydrated) {
            return;
        }

        $ids = [];
        $kitId = (int) $product->getKey();

        foreach ($this->compositionRows as $row) {
            $componentId = (int) ($row['component_product_id'] ?? 0);

            if ($componentId <= 0 || $componentId === $kitId) {
                continue;
            }

            if (ProductCompositionCycleGuard::wouldCreateCycle($kitId, $componentId)) {
                continue;
            }

            $quantidade = $this->parseBrDecimal($row['quantidade'] ?? 0, 3);
            $preco = $this->parseBrDecimal($row['preco'] ?? 0, 2);

            if ($quantidade <= 0) {
                continue;
            }

            $attributes = [
                'component_product_id' => $componentId,
                'quantidade' => $quantidade,
                'preco' => $preco,
                'total' => round($quantidade * $preco, 2),
                'is_adicional' => (bool) ($row['is_adicional'] ?? false),
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
                $recalc = ProductPriceCalculator::recalculateFromCompra([
                    'preco_compra' => $total,
                    'pct_custos' => $product->pct_custos,
                    'pct_lucro' => $product->pct_lucro,
                ]);

                $product->update([
                    'preco_compra' => $total,
                    'preco_custo' => $recalc['preco_custo'],
                    'preco_venda' => $recalc['preco_venda'],
                ]);
            }
        }
    }
}
