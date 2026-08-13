<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\NotaFornecedor;
use Illuminate\Database\Seeder;

class NotaFornecedorSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->where('ativo', true)->orderBy('id')->first();

        $samples = [
            [
                'data_entrada' => now()->subDays(3),
                'data_emissao' => now()->subDays(4),
                'numero' => '1523',
                'chave' => '42260122469772000100550010000152301234567890',
                'cnpj' => '22469772000100',
                'nome' => 'DISTRIBUIDORA CENTRAL LTDA',
                'nsu' => '100001',
                'total' => 4580.75,
                'status' => NotaFornecedor::STATUS_PENDENTE,
            ],
            [
                'data_entrada' => now()->subDays(2),
                'data_emissao' => now()->subDays(2),
                'numero' => '8891',
                'chave' => '42260122333444000155550010000889109876543210',
                'cnpj' => '22333444000155',
                'nome' => 'ATACADO SUL BRASIL ME',
                'nsu' => '100002',
                'total' => 1290.00,
                'status' => NotaFornecedor::STATUS_GEROU_COMPRAS,
            ],
            [
                'data_entrada' => now()->subDay(),
                'data_emissao' => now()->subDay(),
                'numero' => '4402',
                'chave' => '42260122469772000100550010000440201122334455',
                'cnpj' => '22469772000100',
                'nome' => 'DISTRIBUIDORA CENTRAL LTDA',
                'nsu' => '100003',
                'total' => 890.50,
                'status' => NotaFornecedor::STATUS_ACEITA,
            ],
            [
                'data_entrada' => now(),
                'data_emissao' => now(),
                'numero' => '7701',
                'chave' => '42260199887766000155550010000770109988776655',
                'cnpj' => '99887766000155',
                'nome' => 'FORNECEDOR DESCONHECIDO SA',
                'nsu' => '100004',
                'total' => 215.90,
                'status' => NotaFornecedor::STATUS_DESCONHECIDA,
            ],
        ];

        foreach ($samples as $sample) {
            NotaFornecedor::query()->updateOrCreate(
                ['chave' => $sample['chave']],
                [
                    ...$sample,
                    'empresa_id' => $empresa?->id,
                ],
            );
        }
    }
}
