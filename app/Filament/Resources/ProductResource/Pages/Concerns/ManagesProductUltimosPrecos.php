<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;

trait ManagesProductUltimosPrecos
{
    /** @var array<int, array<string, mixed>> */
    public array $priceHistoryRows = [];

    public ?float $lastSavedPrecoVenda = null;

    public ?float $lastSavedPrecoAtacado = null;

    public ?float $lastSavedPrecoEspecial = null;

    public ?float $lastSavedPrecoCusto = null;

    protected function loadProductPriceHistories(?Product $product = null): void
    {
        if (! $product) {
            $this->priceHistoryRows = [];
            $this->lastSavedPrecoVenda = null;
            $this->lastSavedPrecoAtacado = null;
            $this->lastSavedPrecoEspecial = null;
            $this->lastSavedPrecoCusto = null;

            return;
        }

        $this->lastSavedPrecoVenda = (float) $product->preco_venda;
        $this->lastSavedPrecoAtacado = (float) $product->preco_atacado;
        $this->lastSavedPrecoEspecial = (float) $product->preco_especial;
        $this->lastSavedPrecoCusto = (float) $product->preco_custo;

        $this->priceHistoryRows = $product->priceHistories()
            ->orderByDesc('registrado_em')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ProductPriceHistory $history): array => [
                'id' => $history->id,
                'preco_custo' => $this->formatBrDecimal($history->preco_custo ?? 0, 2),
                'ultimo_preco' => $this->formatBrDecimal($history->ultimo_preco, 2),
                'preco_atacado' => $this->formatBrDecimal($history->preco_atacado ?? 0, 2),
                'preco_especial' => $this->formatBrDecimal($history->preco_especial ?? 0, 2),
                'registrado_em' => $history->registrado_em?->format('d/m/Y') ?? '',
                'usuario' => $history->usuario ?? '—',
                'forma_alteracao' => ProductPriceHistoryRecorder::formaLabel($history->forma_alteracao),
            ])
            ->values()
            ->all();
    }

    protected function recordProductPriceHistoryIfChanged(Product $product): void
    {
        (new ProductPriceHistoryRecorder())->recordSalePricesIfChanged(
            product: $product,
            forma: ProductPriceHistoryRecorder::FORMA_DIGITACAO,
            varejoAnterior: $this->lastSavedPrecoVenda,
            atacadoAnterior: $this->lastSavedPrecoAtacado,
            especialAnterior: $this->lastSavedPrecoEspecial,
            custoAnterior: $this->lastSavedPrecoCusto,
        );
    }

    protected function syncProductChildRecords(Product $product): void
    {
        $this->syncProductGrades($product);
        $this->syncProductCompositions($product);
        $this->syncProductPriceTableItems($product);
        $this->syncProductImeis($product);
        $this->syncProductEmpresaPrecos($product);
        $this->recordProductPriceHistoryIfChanged($product);
        $this->loadProductGrades($product);
        $this->loadProductCompositions($product);
        $this->loadProductPriceTableItems($product);
        $this->loadProductImeis($product);
        $this->loadProductPriceHistories($product);
    }
}
