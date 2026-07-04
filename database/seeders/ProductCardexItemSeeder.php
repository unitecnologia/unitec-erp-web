<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Database\Seeder;

class ProductCardexItemSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->orderBy('codigo')->limit(5)->get();

        if ($products->isEmpty()) {
            return;
        }

        $compras = Compra::query()->orderBy('numero')->limit(3)->get();
        $vendas = Venda::query()->orderBy('numero')->limit(4)->get();

        foreach ($compras as $index => $compra) {
            $product = $products[$index % $products->count()];
            $quantidade = 10 + $index;
            $valor = 15.50 + ($index * 2);

            CompraItem::query()->updateOrCreate(
                [
                    'compra_id' => $compra->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valor,
                    'total' => round($quantidade * $valor, 2),
                ],
            );
        }

        foreach ($vendas as $index => $venda) {
            $product = $products[($index + 1) % $products->count()];
            $quantidade = 2 + $index;
            $valor = (float) $product->preco_venda ?: 10;
            $total = round($quantidade * $valor, 2);

            VendaItem::query()->updateOrCreate(
                [
                    'venda_id' => $venda->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantidade' => $quantidade,
                    'valor_item' => $valor,
                    'total' => $total,
                ],
            );
        }
    }
}
