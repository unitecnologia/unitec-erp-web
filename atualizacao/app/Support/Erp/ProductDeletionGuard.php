<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\CompraItem;
use App\Models\NfeItem;
use App\Models\OrcamentoItem;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\VendaItem;

final class ProductDeletionGuard
{
    /**
     * @return list<string>
     */
    public function blockingReasons(Product | int $product): array
    {
        $productId = $product instanceof Product ? (int) $product->getKey() : $product;
        $reasons = [];

        foreach ($this->checks($productId) as $label => $hasLink) {
            if ($hasLink) {
                $reasons[] = $label;
            }
        }

        return $reasons;
    }

    public function canDelete(Product | int $product): bool
    {
        return $this->blockingReasons($product) === [];
    }

    /**
     * @param  list<string>  $reasons
     */
    public function message(array $reasons): string
    {
        if ($reasons === []) {
            return '';
        }

        return 'O produto não pode ser excluído pois possui vínculos com: '
            . implode(', ', $reasons)
            . '.';
    }

    /**
     * @return array<string, bool>
     */
    private function checks(int $productId): array
    {
        return [
            'Compras' => CompraItem::query()->where('product_id', $productId)->exists(),
            'Vendas' => VendaItem::query()->where('product_id', $productId)->exists(),
            'Vendas PDV' => PdvVendaItem::query()->where('product_id', $productId)->exists(),
            'NF-e / NFC-e' => NfeItem::query()->where('product_id', $productId)->exists(),
            'Orçamentos' => OrcamentoItem::query()->where('product_id', $productId)->exists(),
            'Ajustes de estoque' => AjusteEstoque::query()->where('product_id', $productId)->exists(),
            'Composição de outros produtos' => ProductComposition::query()
                ->where('component_product_id', $productId)
                ->exists(),
        ];
    }
}
