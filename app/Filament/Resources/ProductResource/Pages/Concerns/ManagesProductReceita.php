<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductReceita;
use App\Support\Erp\Fabricacao\ProductReceitaCycleGuard;
use App\Support\Erp\Fabricacao\ProductReceitaCusto;
use App\Support\Erp\Fabricacao\ProductReceitaService;
use App\Support\Erp\Os\OsProdutoBusca;
use DomainException;
use Filament\Notifications\Notification;

trait ManagesProductReceita
{
    /** @var array<int, array<string, mixed>> */
    public array $receitaRows = [];

    public string $receitaQtdProduzida = '1,000';

    public bool $receitaAtiva = true;

    public ?int $selectedReceitaIndex = null;

    public string $receitaProductSearch = '';

    public ?int $receitaPendingProductId = null;

    public string $receitaQuantidade = '1,000';

    public bool $productReceitaHydrated = false;

    /** @var array<int, array<string, mixed>> */
    public array $receitaProductSuggestions = [];

    public bool $receitaSuggestionsOpen = false;

    public ?int $receitaSuggestionIndex = null;

    protected bool $receitaMuteSearchHook = false;

    protected function ensureProductReceitaLoaded(): void
    {
        if ($this->productReceitaHydrated) {
            return;
        }

        $product = ($this->record ?? null) instanceof Product ? $this->record : null;
        $this->loadProductReceita($product);
    }

    protected function loadProductReceita(?Product $product = null): void
    {
        $this->productReceitaHydrated = true;

        if (! $product) {
            $this->receitaRows = [];
            $this->receitaQtdProduzida = '1,000';
            $this->receitaAtiva = true;
            $this->clearReceitaEditor(resetSelection: true);

            return;
        }

        /** @var ProductReceita|null $receita */
        $receita = $product->receita()
            ->with(['itens.componentProduct:id,codigo,descricao,unidade,preco_custo,preco_compra'])
            ->first();

        if (! $receita) {
            $this->receitaRows = [];
            $this->receitaQtdProduzida = '1,000';
            $this->receitaAtiva = true;
            $this->clearReceitaEditor(resetSelection: true);

            return;
        }

        $this->receitaQtdProduzida = $this->formatBrDecimal($receita->qtd_produzida, 3);
        $this->receitaAtiva = (bool) ($receita->ativo ?? true);
        $this->receitaRows = $receita->itens
            ->map(function ($item): array {
                $component = $item->componentProduct;
                $custo = $component ? ProductReceitaCusto::unitario($component) : 0.0;
                $qtd = (float) $item->quantidade;

                return [
                    'id' => $item->id,
                    'component_product_id' => $item->component_product_id,
                    'codigo' => $component?->codigo,
                    'descricao' => $component?->descricao,
                    'unidade' => $component?->unidade,
                    'quantidade' => $this->formatBrDecimal($qtd, 3),
                    'custo_unitario' => $this->formatBrDecimal($custo, 2),
                    'custo_total' => $this->formatBrDecimal(round($qtd * $custo, 2), 2),
                ];
            })
            ->values()
            ->all();

        $this->clearReceitaEditor(resetSelection: true);
    }

    public function selectReceitaRow(int $index): void
    {
        if (! isset($this->receitaRows[$index])) {
            return;
        }

        $this->selectedReceitaIndex = $index;
        $row = $this->receitaRows[$index];

        $this->withoutReceitaSearchHook(function () use ($row): void {
            $this->receitaPendingProductId = (int) ($row['component_product_id'] ?? 0) ?: null;
            $this->receitaProductSearch = trim((string) ($row['codigo'] ?? ''));
            $this->receitaQuantidade = (string) ($row['quantidade'] ?? '1,000');
        });

        $this->closeReceitaSuggestions();
    }

    public function clearReceitaSelection(): void
    {
        $this->clearReceitaEditor(resetSelection: true);
    }

    public function updatedReceitaProductSearch(): void
    {
        if ($this->receitaMuteSearchHook) {
            return;
        }

        $term = mb_strtoupper(trim($this->receitaProductSearch), 'UTF-8');

        if ($term !== $this->receitaProductSearch) {
            $this->receitaProductSearch = $term;
        }

        $this->receitaPendingProductId = null;
        $this->refreshReceitaProductSuggestions();
    }

    public function openReceitaSuggestions(): void
    {
        $this->refreshReceitaProductSuggestions();
    }

    public function closeReceitaSuggestions(): void
    {
        $this->receitaProductSuggestions = [];
        $this->receitaSuggestionsOpen = false;
        $this->receitaSuggestionIndex = null;
    }

    public function selectReceitaSuggestion(int $productId): void
    {
        $product = Product::query()
            ->whereKey($productId)
            ->first(['id', 'codigo', 'descricao', 'unidade', 'preco_custo', 'preco_compra', 'ativo', 'is_servico']);

        if (! $product) {
            return;
        }

        $this->withoutReceitaSearchHook(function () use ($product): void {
            $this->receitaPendingProductId = (int) $product->id;
            $this->receitaProductSearch = (string) $product->codigo;
        });

        $this->closeReceitaSuggestions();
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-receita-focus-qtd');
    }

    /**
     * @param  callable(): void  $callback
     */
    protected function withoutReceitaSearchHook(callable $callback): void
    {
        $this->receitaMuteSearchHook = true;

        try {
            $callback();
        } finally {
            $this->receitaMuteSearchHook = false;
        }
    }

    public function confirmReceitaSuggestionFromKeyboard(): void
    {
        if ($this->receitaSuggestionsOpen && $this->receitaProductSuggestions !== []) {
            $index = $this->receitaSuggestionIndex ?? 0;

            if (isset($this->receitaProductSuggestions[$index])) {
                $this->selectReceitaSuggestion((int) $this->receitaProductSuggestions[$index]['id']);

                return;
            }
        }

        $component = $this->resolveReceitaComponent();

        if ($component) {
            $this->selectReceitaSuggestion((int) $component->getKey());
        }
    }

    public function confirmReceitaFromQtd(?string $quantidade = null): void
    {
        if ($quantidade !== null && trim($quantidade) !== '') {
            $this->receitaQuantidade = $quantidade;
        }

        if ($this->selectedReceitaIndex !== null) {
            $this->updateReceitaItem();
        } else {
            $this->addReceitaItem();
        }
    }

    public function addReceitaItem(): void
    {
        $this->commitReceitaItem(updateSelected: false);
    }

    public function updateReceitaItem(): void
    {
        if ($this->selectedReceitaIndex === null || ! isset($this->receitaRows[$this->selectedReceitaIndex])) {
            Notification::make()
                ->title('Selecione um ingrediente da receita para atualizar.')
                ->warning()
                ->send();

            return;
        }

        $this->commitReceitaItem(updateSelected: true);
    }

    public function deleteReceitaItem(): void
    {
        if ($this->selectedReceitaIndex === null || ! isset($this->receitaRows[$this->selectedReceitaIndex])) {
            Notification::make()
                ->title('Selecione um ingrediente da receita.')
                ->warning()
                ->send();

            return;
        }

        unset($this->receitaRows[$this->selectedReceitaIndex]);
        $this->receitaRows = array_values($this->receitaRows);
        $this->clearReceitaEditor(resetSelection: true);
    }

    public function receitaCustoTotal(): float
    {
        return collect($this->receitaRows)
            ->sum(fn (array $row): float => $this->parseBrDecimal($row['custo_total'] ?? 0, 2));
    }

    public function receitaCustoUnitarioProduzido(): float
    {
        $rendimento = $this->parseBrDecimal($this->receitaQtdProduzida ?: '0', 3);
        $total = $this->receitaCustoTotal();

        if ($rendimento <= 0.0000001) {
            return 0.0;
        }

        return round($total / $rendimento, 2);
    }

    protected function commitReceitaItem(bool $updateSelected): void
    {
        $component = $this->resolveReceitaComponent();

        if (! $component) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Informe o código ou o nome de um produto válido.')
                ->warning()
                ->send();

            return;
        }

        if ($component->is_servico) {
            Notification::make()
                ->title('Serviço não pode ser ingrediente.')
                ->warning()
                ->send();

            return;
        }

        if (! ($component->ativo ?? false)) {
            Notification::make()
                ->title('Ingrediente inativo não permitido.')
                ->warning()
                ->send();

            return;
        }

        $acabadoId = $this->isEditingProduct() ? (int) ($this->record?->getKey() ?? 0) : 0;

        if ($acabadoId > 0 && (int) $component->getKey() === $acabadoId) {
            Notification::make()
                ->title('O produto acabado não pode ser ingrediente da própria receita.')
                ->warning()
                ->send();

            return;
        }

        if ($acabadoId > 0 && ProductReceitaCycleGuard::wouldCreateCycle($acabadoId, (int) $component->getKey())) {
            Notification::make()
                ->title('Receita circular não permitida.')
                ->body('Este produto já depende do item atual (direta ou indiretamente).')
                ->warning()
                ->send();

            return;
        }

        $quantidade = $this->parseBrDecimal($this->receitaQuantidade ?: '1', 3);

        if ($quantidade <= 0) {
            Notification::make()
                ->title('Quantidade inválida.')
                ->body('Informe uma quantidade decimal maior que zero.')
                ->warning()
                ->send();

            return;
        }

        $custo = ProductReceitaCusto::unitario($component);
        $total = round($quantidade * $custo, 2);

        $payload = [
            'id' => null,
            'component_product_id' => $component->getKey(),
            'codigo' => $component->codigo,
            'descricao' => $component->descricao,
            'unidade' => $component->unidade,
            'quantidade' => $this->formatBrDecimal($quantidade, 3),
            'custo_unitario' => $this->formatBrDecimal($custo, 2),
            'custo_total' => $this->formatBrDecimal($total, 2),
        ];

        $existingIndex = collect($this->receitaRows)
            ->search(fn (array $row): bool => (int) ($row['component_product_id'] ?? 0) === (int) $component->getKey());

        if ($updateSelected) {
            $selected = (int) $this->selectedReceitaIndex;

            if ($existingIndex !== false && (int) $existingIndex !== $selected) {
                Notification::make()
                    ->title('Ingrediente já está na receita.')
                    ->body('Selecione a linha existente para alterar a quantidade.')
                    ->warning()
                    ->send();

                return;
            }

            $payload['id'] = $this->receitaRows[$selected]['id'] ?? null;
            $this->receitaRows[$selected] = $payload;
        } elseif ($existingIndex !== false) {
            Notification::make()
                ->title('Ingrediente já está na receita.')
                ->body('Selecione a linha para alterar a quantidade.')
                ->warning()
                ->send();

            return;
        } else {
            $this->receitaRows[] = $payload;
        }

        $this->receitaRows = array_values($this->receitaRows);
        $this->clearReceitaEditor(resetSelection: true);
        $this->dispatch('erp-receita-focus-search');
    }

    protected function resolveReceitaComponent(): ?Product
    {
        if ($this->receitaPendingProductId) {
            return Product::query()
                ->whereKey($this->receitaPendingProductId)
                ->first(['id', 'codigo', 'descricao', 'unidade', 'preco_custo', 'preco_compra', 'ativo', 'is_servico']);
        }

        $term = trim($this->receitaProductSearch);

        if ($term === '') {
            return null;
        }

        $query = Product::query()->select(['id', 'codigo', 'descricao', 'unidade', 'preco_custo', 'preco_compra', 'ativo', 'is_servico']);
        OsProdutoBusca::aplicar($query, $term);

        return $query->limit(1)->first();
    }

    protected function refreshReceitaProductSuggestions(): void
    {
        $term = trim($this->receitaProductSearch);

        if (mb_strlen($term) < 1) {
            $this->receitaProductSuggestions = [];
            $this->receitaSuggestionsOpen = false;
            $this->receitaSuggestionIndex = null;

            return;
        }

        $acabadoId = $this->isEditingProduct() ? (int) ($this->record?->getKey() ?? 0) : 0;

        $query = Product::query()
            ->where('ativo', true)
            ->where('is_servico', false)
            ->when($acabadoId > 0, fn ($q) => $q->whereKeyNot($acabadoId))
            ->select(['id', 'codigo', 'descricao', 'unidade', 'preco_custo', 'preco_compra']);

        OsProdutoBusca::aplicar($query, $term);

        $this->receitaProductSuggestions = $query
            ->limit(12)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'unidade' => (string) ($product->unidade ?? ''),
                'custo' => number_format(ProductReceitaCusto::unitario($product), 2, ',', '.'),
            ])
            ->values()
            ->all();

        $this->receitaSuggestionsOpen = $this->receitaProductSuggestions !== [];
        $this->receitaSuggestionIndex = $this->receitaProductSuggestions === [] ? null : 0;
    }

    protected function clearReceitaEditor(bool $resetSelection = false): void
    {
        if ($resetSelection) {
            $this->selectedReceitaIndex = null;
        }

        $this->withoutReceitaSearchHook(function (): void {
            $this->receitaProductSearch = '';
            $this->receitaPendingProductId = null;
            $this->receitaQuantidade = '1,000';
        });

        $this->closeReceitaSuggestions();
    }

    /**
     * Persiste a receita sem alterar preços/custos do cadastro do produto.
     */
    protected function syncProductReceita(Product $product): void
    {
        if (! $this->productReceitaHydrated) {
            return;
        }

        if ($product->is_servico) {
            (new ProductReceitaService())->clear((int) $product->getKey());

            return;
        }

        $itens = [];

        foreach ($this->receitaRows as $row) {
            $componentId = (int) ($row['component_product_id'] ?? 0);
            $quantidade = $this->parseBrDecimal($row['quantidade'] ?? 0, 3);

            if ($componentId <= 0 || $quantidade <= 0) {
                continue;
            }

            $itens[] = [
                'component_product_id' => $componentId,
                'quantidade' => $quantidade,
            ];
        }

        $service = new ProductReceitaService();

        try {
            if ($itens === []) {
                $service->clear((int) $product->getKey());

                return;
            }

            $service->sync(
                $product,
                $this->parseBrDecimal($this->receitaQtdProduzida ?: '0', 3),
                $itens,
                $this->receitaAtiva,
            );
        } catch (DomainException $e) {
            Notification::make()
                ->title('Receita não gravada.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
