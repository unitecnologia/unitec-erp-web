<?php

namespace Database\Seeders;

use App\Models\AjusteEstoque;
use App\Models\Product;
use Illuminate\Database\Seeder;

class AjusteEstoqueSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->orderBy('codigo')->limit(3)->get();

        foreach ($products as $index => $product) {
            AjusteEstoque::query()->updateOrCreate(
                [
                    'data' => now()->subDays(2 - $index)->toDateString(),
                    'product_id' => $product->id,
                ],
                [
                    'qtd_ajust' => ($index + 1) * 5,
                ],
            );
        }
    }
}
