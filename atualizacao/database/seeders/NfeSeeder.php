<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Person;
use Illuminate\Database\Seeder;

class NfeSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->where('ativo', true)->orderBy('id')->first();
        $cliente = Person::query()->where('is_cliente', true)->orderBy('id')->first();

        if (! $empresa || ! $cliente) {
            return;
        }

        $samples = [
            [
                'numero' => '1',
                'serie' => '1',
                'data_emissao' => '2026-06-01',
                'data_saida' => '2026-06-01',
                'chave' => null,
                'protocolo' => null,
                'total' => 538.70,
                'status' => Nfe::STATUS_ABERTA,
                'itens' => [
                    ['descricao' => 'PRODUTO DEMONSTRACAO A', 'quantidade' => 2, 'valor_unitario' => 149.90, 'total' => 299.80],
                    ['descricao' => 'PRODUTO DEMONSTRACAO B', 'quantidade' => 1, 'valor_unitario' => 238.90, 'total' => 238.90],
                ],
            ],
            [
                'numero' => '2',
                'serie' => '1',
                'data_emissao' => '2026-06-10',
                'data_saida' => '2026-06-10',
                'chave' => '42260614200166000187550010000000021234567890',
                'protocolo' => '142260000123456',
                'total' => 1000.00,
                'status' => Nfe::STATUS_TRANSMITIDA,
                'itens' => [
                    ['descricao' => 'SERVICO DE INSTALACAO', 'quantidade' => 1, 'valor_unitario' => 1000.00, 'total' => 1000.00],
                ],
            ],
            [
                'numero' => '3',
                'serie' => '1',
                'data_emissao' => '2026-06-15',
                'data_saida' => '2026-06-16',
                'chave' => '42260614200166000187550010000000039876543210',
                'protocolo' => '142260000987654',
                'total' => 0.00,
                'status' => Nfe::STATUS_TRANSMITIDA,
                'itens' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $itens = $sample['itens'];
            unset($sample['itens']);

            $nfe = Nfe::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'numero' => $sample['numero'],
                    'serie' => $sample['serie'],
                ],
                [
                    ...$sample,
                    'empresa_id' => $empresa->id,
                    'cliente_id' => $cliente->id,
                ],
            );

            $nfe->itens()->delete();

            foreach ($itens as $item) {
                NfeItem::query()->create([
                    'nfe_id' => $nfe->id,
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'total' => $item['total'],
                ]);
            }
        }
    }
}
