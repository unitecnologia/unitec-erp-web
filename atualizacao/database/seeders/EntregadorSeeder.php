<?php

namespace Database\Seeders;

use App\Models\Entregador;
use Illuminate\Database\Seeder;

class EntregadorSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['codigo' => '1', 'nome' => 'JOÃO ENTREGAS RÁPIDAS'],
            ['codigo' => '2', 'nome' => 'MOTOBOY CENTRAL'],
            ['codigo' => '3', 'nome' => 'TRANSPORTE EXPRESSO'],
        ];

        foreach ($samples as $sample) {
            Entregador::query()->updateOrCreate(
                ['codigo' => $sample['codigo']],
                ['nome' => $sample['nome']],
            );
        }
    }
}
