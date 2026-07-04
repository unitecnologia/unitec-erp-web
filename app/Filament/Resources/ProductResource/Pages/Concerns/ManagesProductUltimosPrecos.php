<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\Auth;

trait ManagesProductUltimosPrecos
{
    /** @var array<int, array<string, mixed>> */
    public array $priceHistoryRows = [];

    public ?float $lastSavedPrecoVenda = null;

    protected function loadProductPriceHistories(?Product $product = null): void
    {
        if (! $product) {
            $this->priceHistoryRows = [];
            $this->lastSavedPrecoVenda = null;

            return;
        }

        $this->lastSavedPrecoVenda = (float) $product->preco_venda;

        $this->priceHistoryRows = $product->priceHistories()
            ->orderByDesc('registrado_em')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ProductPriceHistory $history): array => [
                'id' => $history->id,
                'ultimo_preco' => $this->formatBrDecimal($history->ultimo_preco, 2),
                'registrado_em' => $history->registrado_em?->format('d/m/Y') ?? '',
                'usuario' => $history->usuario ?? '—',
            ])
            ->values()
            ->all();
    }

    protected function recordProductPriceHistoryIfChanged(Product $product, ?float $previousPrice = null): void
    {
        $previous = $previousPrice ?? $this->lastSavedPrecoVenda;
        $current = (float) $product->preco_venda;

        if ($previous === null || round($previous, 2) === round($current, 2)) {
            return;
        }

        $product->priceHistories()->create([
            'ultimo_preco' => $previous,
            'registrado_em' => now()->toDateString(),
            'usuario' => Auth::user()?->name ?? 'Sistema',
        ]);
    }

    protected function syncProductChildRecords(Product $product): void
    {
        $this->syncProductGrades($product);
        $this->syncProductCompositions($product);
        $this->syncProductPriceTableItems($product);
        $this->syncProductImeis($product);
        $this->recordProductPriceHistoryIfChanged($product);
        $this->loadProductGrades($product);
        $this->loadProductCompositions($product);
        $this->loadProductPriceTableItems($product);
        $this->loadProductImeis($product);
        $this->loadProductPriceHistories($product);
    }
}
