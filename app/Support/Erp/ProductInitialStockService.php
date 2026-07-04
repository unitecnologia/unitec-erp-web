<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\Product;

class ProductInitialStockService
{
    public static function registerFromInitialStock(Product $product): void
    {
        $qty = (float) $product->estoque_inicial;

        if ($qty <= 0) {
            return;
        }

        $alreadyRegistered = AjusteEstoque::query()
            ->where('product_id', $product->getKey())
            ->exists();

        if ($alreadyRegistered) {
            return;
        }

        AjusteEstoque::query()->create([
            'data' => now()->toDateString(),
            'product_id' => $product->getKey(),
            'qtd_ajust' => $qty,
        ]);
    }
}
