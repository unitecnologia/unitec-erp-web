<?php

namespace Database\Seeders;

use App\Models\Contador;
use Illuminate\Database\Seeder;

class ContadorSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['codigo' => '1', 'nome' => 'EBSON CONTADOR'],
            ['codigo' => '2', 'nome' => 'CONTABILIDADE ALPHA LTDA'],
        ];

        foreach ($samples as $sample) {
            Contador::query()->updateOrCreate(
                ['codigo' => $sample['codigo']],
                ['nome' => $sample['nome']],
            );
        }
    }
}
