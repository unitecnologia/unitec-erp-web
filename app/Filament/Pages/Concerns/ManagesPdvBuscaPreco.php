<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\ErpMoney;

trait ManagesPdvBuscaPreco
{
    public string $buscaPrecoSearch = '';

    /** @var array<string, mixed>|null */
    public ?array $buscaPrecoResult = null;

    public function openBuscaPrecoModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->buscaPrecoSearch = trim($this->pdvSearch);
        $this->buscaPrecoResult = null;
        $this->refreshBuscaPrecoResult();
        $this->openPdvModal('busca_preco');
        $this->dispatch('erp-pdv-focus-busca-preco');
    }

    public function updatedBuscaPrecoSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->buscaPrecoSearch !== $upper) {
            $this->buscaPrecoSearch = $upper;
        }

        $this->refreshBuscaPrecoResult();
    }

    public function refreshBuscaPrecoResult(): void
    {
        $term = trim($this->buscaPrecoSearch);

        if ($term === '') {
            $this->buscaPrecoResult = null;

            return;
        }

        $parsed = $this->parsePdvSearchTerm($term);
        $searchTerm = $parsed['term'];
        $product = $this->findExactProductForPdv($searchTerm);

        if (! $product) {
            $results = $this->queryProductsForPdv($searchTerm);
            $productId = $results[0]['product_id'] ?? null;
            $product = filled($productId) ? Product::query()->find($productId) : null;
        }

        if (! $product) {
            $this->buscaPrecoResult = null;

            return;
        }

        $priceService = $this->pdvPriceService();
        $precoVenda = $priceService->resolvePrecoVenda($product, 1);
        $precoTabela = $priceService->resolvePrecoTabela($product, 1);

        $this->buscaPrecoResult = [
            'codigo' => $product->codigo,
            'referencia' => $product->referencia ?? '',
            'codigo_barras' => $product->codigo_barras ?? '',
            'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            'unidade' => $product->unidade ?: 'UN',
            'estoque' => (float) $product->estoque,
            'preco_venda' => $precoVenda,
            'preco_tabela' => $precoTabela,
            'em_promocao' => $priceService->emPromocao($product),
        ];
    }

    public function confirmBuscaPreco(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelBuscaPreco(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function getBuscaPrecoPrecoVendaFormatadoProperty(): string
    {
        return ErpMoney::formatBr($this->buscaPrecoResult['preco_venda'] ?? 0);
    }

    public function getBuscaPrecoPrecoTabelaFormatadoProperty(): ?string
    {
        $preco = $this->buscaPrecoResult['preco_tabela'] ?? null;

        return $preco !== null ? ErpMoney::formatBr($preco) : null;
    }
}
