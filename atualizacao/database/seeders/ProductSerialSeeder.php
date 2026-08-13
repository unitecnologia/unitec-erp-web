<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Database\Seeder;

class ProductSerialSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->where('usa_imei', true)
            ->orWhere('descricao', 'like', '%NOTEBOOK%')
            ->orWhere('descricao', 'like', '%CELULAR%')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $products = Product::query()->limit(3)->get();
        }

        foreach ($products as $index => $product) {
            foreach (range(1, 2) as $serialIndex) {
                ProductSerial::query()->firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'numero_serie' => sprintf('SN-%s-%02d', $product->codigo, $serialIndex),
                    ],
                    [
                        'situacao' => $serialIndex === 1 ? 'DISPONIVEL' : 'VENDIDO',
                        'doc_saida' => $serialIndex === 1 ? null : 'PDV-000001',
                        'data_baixa' => $serialIndex === 1 ? null : now()->subDays($index + 1)->toDateString(),
                    ],
                );
            }
        }
    }
}
