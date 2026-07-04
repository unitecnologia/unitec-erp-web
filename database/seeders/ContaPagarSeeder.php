<?php

namespace Database\Seeders;

use App\Models\ContaPagar;
use App\Models\Person;
use Illuminate\Database\Seeder;

class ContaPagarSeeder extends Seeder
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
                'numero' => '000201',
                'emissao' => now()->subDays(12),
                'produto' => 'MERCADORIA PARA REVENDA',
                'documento' => 'NF 4521',
                'fornecedor' => '12',
                'vencimento' => now()->addDays(8),
                'valor' => 3200.00,
                'desconto' => 0,
                'juros' => 0,
                'valor_pago' => 0,
                'pago_em' => null,
            ],
            [
                'numero' => '000202',
                'emissao' => now()->subDays(18),
                'produto' => 'INSUMOS DE EMBALAGEM',
                'documento' => 'NF 8891',
                'fornecedor' => '31',
                'vencimento' => now()->subDays(2),
                'valor' => 890.50,
                'desconto' => 40.00,
                'juros' => 12.50,
                'valor_pago' => 0,
                'pago_em' => null,
            ],
            [
                'numero' => '000203',
                'emissao' => now()->subDays(25),
                'produto' => 'EQUIPAMENTOS DE INFORMÁTICA',
                'documento' => 'NF 1102',
                'fornecedor' => '12',
                'vencimento' => now()->subDays(15),
                'valor' => 1580.00,
                'desconto' => 0,
                'juros' => 0,
                'valor_pago' => 1580.00,
                'pago_em' => now()->subDays(10),
            ],
            [
                'numero' => '000204',
                'emissao' => now()->subDays(5),
                'produto' => 'MATERIAL DE LIMPEZA',
                'documento' => 'NF 7730',
                'fornecedor' => '31',
                'vencimento' => now()->addDays(15),
                'valor' => 245.90,
                'desconto' => 5.90,
                'juros' => 0,
                'valor_pago' => 0,
                'pago_em' => null,
            ],
        ];

        foreach ($samples as $sample) {
            $fornecedor = $fornecedores->get($sample['fornecedor']) ?? $fornecedorPadrao;

            ContaPagar::query()->updateOrCreate(
                ['numero' => $sample['numero']],
                [
                    'emissao' => $sample['emissao'],
                    'produto' => $sample['produto'],
                    'documento' => $sample['documento'],
                    'fornecedor_id' => $fornecedor->id,
                    'vencimento' => $sample['vencimento'],
                    'valor' => $sample['valor'],
                    'desconto' => $sample['desconto'],
                    'juros' => $sample['juros'],
                    'valor_pago' => $sample['valor_pago'],
                    'pago_em' => $sample['pago_em'],
                ],
            );
        }
    }
}
