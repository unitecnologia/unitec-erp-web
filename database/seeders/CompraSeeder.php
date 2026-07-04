<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\Person;
use Illuminate\Database\Seeder;

class CompraSeeder extends Seeder
{
    public function run(): void
    {
        $fornecedores = Person::query()->where('is_fornecedor', true)->get()->keyBy('codigo');
        $fornecedorPadrao = $fornecedores->first();

        if (! $fornecedorPadrao) {
            return;
        }

        $samples = [
            [
                'numero' => '000001',
                'data_emissao' => now()->subDays(8),
                'data_entrada' => now()->subDays(7),
                'numero_nota' => '1523',
                'fornecedor' => '12',
                'chave_nfe' => '42260122469772000100550010000152301234567890',
                'total' => 4580.75,
                'status' => Compra::STATUS_FECHADA,
            ],
            [
                'numero' => '000002',
                'data_emissao' => now()->subDays(4),
                'data_entrada' => now()->subDays(3),
                'numero_nota' => '8891',
                'fornecedor' => '31',
                'chave_nfe' => '42260122333444000155550010000889109876543210',
                'total' => 1290.00,
                'status' => Compra::STATUS_ABERTA,
            ],
            [
                'numero' => '000003',
                'data_emissao' => now()->subDay(),
                'data_entrada' => null,
                'numero_nota' => '9012',
                'fornecedor' => '12',
                'chave_nfe' => null,
                'total' => 320.40,
                'status' => Compra::STATUS_ABERTA,
            ],
            [
                'numero' => '000004',
                'data_emissao' => now()->subDays(10),
                'data_entrada' => now()->subDays(9),
                'numero_nota' => '4401',
                'fornecedor' => '31',
                'chave_nfe' => '42260122333444000155550010000440101122334455',
                'total' => 890.50,
                'status' => Compra::STATUS_CANCELADA,
            ],
        ];

        foreach ($samples as $sample) {
            $fornecedor = $fornecedores->get($sample['fornecedor']) ?? $fornecedorPadrao;

            Compra::query()->updateOrCreate(
                ['numero' => $sample['numero']],
                [
                    'data_emissao' => $sample['data_emissao'],
                    'data_entrada' => $sample['data_entrada'],
                    'numero_nota' => $sample['numero_nota'],
                    'fornecedor_id' => $fornecedor->id,
                    'chave_nfe' => $sample['chave_nfe'],
                    'total' => $sample['total'],
                    'status' => $sample['status'],
                ],
            );
        }
    }
}
