<?php

namespace Database\Seeders;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\Vendedor;
use Illuminate\Database\Seeder;

class OrcamentoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = Person::query()->where('is_cliente', true)->get()->keyBy('codigo');
        $vendedores = Vendedor::query()->get()->keyBy('codigo');
        $products = Product::query()->orderBy('codigo')->get()->keyBy('codigo');

        $clientePadrao = $clientes->first();
        $vendedorPadrao = $vendedores->first();

        if (! $clientePadrao || $products->isEmpty()) {
            return;
        }

        $samples = [
            [
                'numero' => '000001',
                'data' => now()->subDays(5),
                'cliente' => '4',
                'vendedor' => '13',
                'status' => Orcamento::STATUS_ABERTO,
                'itens' => [
                    ['codigo' => '2', 'qtd' => 10, 'preco' => 1.00],
                    ['codigo' => '3', 'qtd' => 5, 'preco' => 1.00],
                    ['codigo' => '1', 'qtd' => 1, 'preco' => 221.54],
                ],
            ],
            [
                'numero' => '000002',
                'data' => now()->subDays(3),
                'cliente' => '30',
                'vendedor' => '13',
                'status' => Orcamento::STATUS_FECHADO,
                'itens' => [
                    ['codigo' => '4', 'qtd' => 20, 'preco' => 1.00],
                ],
            ],
            [
                'numero' => '000003',
                'data' => now()->subDays(2),
                'cliente' => '33',
                'vendedor' => '32',
                'status' => Orcamento::STATUS_ABERTO,
                'itens' => [
                    ['codigo' => '5', 'qtd' => 100, 'preco' => 1.00],
                    ['codigo' => '6', 'qtd' => 50, 'preco' => 1.00],
                ],
            ],
            [
                'numero' => '000004',
                'data' => now()->subDay(),
                'cliente' => '11',
                'vendedor' => '32',
                'status' => Orcamento::STATUS_CANCELADO,
                'itens' => [],
            ],
            [
                'numero' => '000005',
                'data' => now(),
                'cliente' => '4',
                'vendedor' => '13',
                'status' => Orcamento::STATUS_IMPORTADO,
                'itens' => [
                    ['codigo' => '7', 'qtd' => 2, 'preco' => 1.00],
                ],
            ],
        ];

        foreach ($samples as $sample) {
            $cliente = $clientes->get($sample['cliente']) ?? $clientePadrao;
            $vendedor = $vendedores->get($sample['vendedor'] ?? '') ?? $vendedorPadrao;

            $total = 0.0;

            foreach ($sample['itens'] as $itemSample) {
                $total += round($itemSample['qtd'] * $itemSample['preco'], 2);
            }

            $orcamento = Orcamento::query()->updateOrCreate(
                ['numero' => $sample['numero']],
                [
                    'data' => $sample['data'],
                    'cliente_id' => $cliente->id,
                    'vendedor_id' => $vendedor?->id,
                    'total' => $total > 0 ? $total : ($sample['numero'] === '000004' ? 560.00 : $total),
                    'status' => $sample['status'],
                ],
            );

            $orcamento->itens()->delete();

            foreach ($sample['itens'] as $itemSample) {
                $product = $products->get($itemSample['codigo']);

                if (! $product) {
                    continue;
                }

                $qtd = (float) $itemSample['qtd'];
                $preco = (float) $itemSample['preco'];

                OrcamentoItem::query()->create([
                    'orcamento_id' => $orcamento->id,
                    'product_id' => $product->id,
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'total' => round($qtd * $preco, 2),
                    'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
                ]);
            }
        }
    }
}
