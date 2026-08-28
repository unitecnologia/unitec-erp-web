<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvProductSearchRanking;

trait ManagesPdvBuscaAvancada
{
    public string $buscaAvancadaSearch = '';

    public string $buscaAvancadaColumn = 'codigo_barras';

    /** @var array<int, array<string, mixed>> */
    public array $buscaAvancadaResults = [];

    public ?int $selectedBuscaAvancadaIndex = null;

    public function openBuscaAvancadaModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->buscaAvancadaColumn = 'codigo_barras';
        $this->buscaAvancadaSearch = trim($this->pdvSearch);
        $this->refreshBuscaAvancadaResults();
        $this->openPdvModal('busca_avancada');
        $this->dispatch('erp-pdv-focus-busca-avancada');
    }

    public function setBuscaAvancadaColumn(string $column): void
    {
        if (! in_array($column, ['descricao', 'codigo', 'referencia', 'codigo_barras'], true)) {
            return;
        }

        $this->buscaAvancadaColumn = $column;
        $this->refreshBuscaAvancadaResults();
    }

    public function updatedBuscaAvancadaSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->buscaAvancadaSearch !== $upper) {
            $this->buscaAvancadaSearch = $upper;
        }

        $this->refreshBuscaAvancadaResults();
    }

    public function refreshBuscaAvancadaResults(): void
    {
        $term = trim($this->buscaAvancadaSearch);

        if ($term === '') {
            $this->buscaAvancadaResults = [];
            $this->selectedBuscaAvancadaIndex = null;

            return;
        }

        $config = $this->pdvConfig();
        $like = $config->pesquisaPartesDescricao() ? '%' . $term . '%' : $term . '%';
        $column = $this->buscaAvancadaColumn;
        $empresaId = $config->empresa()?->id ? (int) $config->empresa()->id : null;

        $prefixAttributes = match ($column) {
            'codigo' => ['codigo'],
            'referencia' => ['referencia'],
            'codigo_barras' => ['codigo_barras', 'codigo_barras_caixa'],
            default => ['descricao'],
        };

        $query = Product::query()->where('ativo', true);

        $query->where(function ($q) use ($column, $like): void {
            match ($column) {
                'codigo' => $q->where('codigo', 'like', $like),
                'referencia' => $q->where('referencia', 'like', $like),
                'codigo_barras' => $q->where(function ($sub) use ($like): void {
                    $sub->where('codigo_barras', 'like', $like)
                        ->orWhere('codigo_barras_caixa', 'like', $like);
                }),
                default => $q->where('descricao', 'like', $like),
            };
        });

        $candidates = $query
            ->orderBy('descricao')
            ->limit(PdvProductSearchRanking::CANDIDATE_LIMIT)
            ->get();

        $ranked = PdvProductSearchRanking::rankProducts(
            $candidates,
            $term,
            $prefixAttributes,
            $empresaId,
            100,
        );

        $priceService = $this->pdvPriceService();

        $previousProductId = 0;
        if ($this->selectedBuscaAvancadaIndex !== null) {
            $previousProductId = (int) ($this->buscaAvancadaResults[$this->selectedBuscaAvancadaIndex]['product_id'] ?? 0);
        }

        $this->buscaAvancadaResults = $ranked
            ->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'codigo' => $product->codigo,
                'referencia' => $product->referencia ?? '',
                'codigo_barras' => $product->codigo_barras ?? '',
                'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
                'preco' => $priceService->resolvePrecoVenda($product, 1),
                'estoque' => (float) $product->estoque,
                'unidade' => $product->unidade ?: 'UN',
            ])
            ->values()
            ->all();

        $this->selectedBuscaAvancadaIndex = null;

        if ($previousProductId > 0) {
            foreach ($this->buscaAvancadaResults as $index => $row) {
                if ((int) ($row['product_id'] ?? 0) === $previousProductId) {
                    $this->selectedBuscaAvancadaIndex = $index;
                    break;
                }
            }
        }

        if ($this->selectedBuscaAvancadaIndex === null) {
            $this->selectedBuscaAvancadaIndex = $this->buscaAvancadaResults === [] ? null : 0;
        }
    }

    public function selectBuscaAvancadaResult(int $index): void
    {
        if (isset($this->buscaAvancadaResults[$index])) {
            $this->selectedBuscaAvancadaIndex = $index;
        }
    }

    public function moveBuscaAvancadaSelection(int $delta): void
    {
        if ($this->buscaAvancadaResults === []) {
            return;
        }

        $count = count($this->buscaAvancadaResults);
        $index = ($this->selectedBuscaAvancadaIndex ?? 0) + $delta;
        $this->selectedBuscaAvancadaIndex = max(0, min($count - 1, $index));
    }

    public function confirmBuscaAvancada(): void
    {
        $index = $this->selectedBuscaAvancadaIndex;

        if ($index === null || ! isset($this->buscaAvancadaResults[$index])) {
            $this->notifyPdvError('Selecione um produto.');

            return;
        }

        $row = $this->buscaAvancadaResults[$index];
        $this->pdvSearch = $row['descricao'] ?? '';
        $this->closePdvModal();
        $this->refreshPdvSearchResults();

        foreach ($this->pdvSearchResults as $i => $result) {
            if ((int) ($result['product_id'] ?? 0) === (int) ($row['product_id'] ?? 0)) {
                $this->selectedSearchIndex = $i;
                break;
            }
        }

        $this->syncLaunchFieldsFromSelection();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelBuscaAvancada(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
