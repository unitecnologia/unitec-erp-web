<?php

namespace Database\Seeders;

use App\Models\ContaReceber;
use App\Models\Person;
use Illuminate\Database\Seeder;

class ContaReceberSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = Person::query()->where('is_cliente', true)->get()->keyBy('codigo');
        $clientePadrao = $clientes->first();

        if (! $clientePadrao) {
            return;
        }

        $samples = [
            [
                'numero' => '000101',
                'emissao' => now()->subDays(15),
                'historico' => 'VENDA PDV 1523',
                'documento' => 'NF 1523',
                'cliente' => '4',
                'vencimento' => now()->addDays(5),
                'valor' => 850.00,
                'desconto' => 0,
                'juros' => 0,
                'valor_recebido' => 0,
                'recebido_em' => null,
                'forma' => ContaReceber::FORMA_BOLETO,
            ],
            [
                'numero' => '000102',
                'emissao' => now()->subDays(20),
                'historico' => 'PARCELA 1/3 ORÇAMENTO',
                'documento' => 'ORC 000003',
                'cliente' => '30',
                'vencimento' => now()->subDays(3),
                'valor' => 1200.00,
                'desconto' => 50.00,
                'juros' => 25.00,
                'valor_recebido' => 0,
                'recebido_em' => null,
                'forma' => ContaReceber::FORMA_CARTEIRA,
            ],
            [
                'numero' => '000103',
                'emissao' => now()->subDays(30),
                'historico' => 'VENDA A PRAZO',
                'documento' => 'VD 000102',
                'cliente' => '33',
                'vencimento' => now()->subDays(10),
                'valor' => 560.75,
                'desconto' => 0,
                'juros' => 15.25,
                'valor_recebido' => 576.00,
                'recebido_em' => now()->subDays(8),
                'forma' => ContaReceber::FORMA_CARTAO,
            ],
            [
                'numero' => '000104',
                'emissao' => now()->subDays(8),
                'historico' => 'CHEQUE CLIENTE',
                'documento' => 'CH 8891',
                'cliente' => '11',
                'vencimento' => now()->addDays(12),
                'valor' => 980.00,
                'desconto' => 0,
                'juros' => 0,
                'valor_recebido' => 0,
                'recebido_em' => null,
                'forma' => ContaReceber::FORMA_CHEQUE,
            ],
        ];

        foreach ($samples as $sample) {
            $cliente = $clientes->get($sample['cliente']) ?? $clientePadrao;

            ContaReceber::query()->updateOrCreate(
                ['numero' => $sample['numero']],
                [
                    'emissao' => $sample['emissao'],
                    'historico' => $sample['historico'],
                    'documento' => $sample['documento'],
                    'cliente_id' => $cliente->id,
                    'vencimento' => $sample['vencimento'],
                    'valor' => $sample['valor'],
                    'desconto' => $sample['desconto'],
                    'juros' => $sample['juros'],
                    'valor_recebido' => $sample['valor_recebido'],
                    'recebido_em' => $sample['recebido_em'],
                    'forma' => $sample['forma'],
                ],
            );
        }
    }
}
