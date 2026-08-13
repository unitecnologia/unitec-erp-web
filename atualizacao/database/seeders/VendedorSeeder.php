<?php

namespace Database\Seeders;

use App\Models\Vendedor;
use Illuminate\Database\Seeder;

class VendedorSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['codigo' => '1', 'nome' => 'LOJA', 'ativo' => true, 'comissao_av' => 0, 'comissao_ap' => 0],
            ['codigo' => '13', 'nome' => 'ADEMAR COSTA SILVA', 'ativo' => true, 'comissao_av' => 2.5, 'comissao_ap' => 3.0],
            ['codigo' => '32', 'nome' => 'MARIA OLIVEIRA SANTOS', 'ativo' => true, 'comissao_av' => 1.5, 'comissao_ap' => 2.0],
            ['codigo' => '45', 'nome' => 'CARLOS MENDES', 'ativo' => false, 'comissao_av' => 0, 'comissao_ap' => 0],
        ];

        foreach ($samples as $sample) {
            Vendedor::query()->updateOrCreate(
                ['codigo' => $sample['codigo']],
                [
                    'nome' => $sample['nome'],
                    'ativo' => $sample['ativo'],
                    'comissao_av' => $sample['comissao_av'],
                    'comissao_ap' => $sample['comissao_ap'],
                ],
            );
        }
    }
}
