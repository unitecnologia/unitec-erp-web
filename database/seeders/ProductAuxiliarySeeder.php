<?php

namespace Database\Seeders;

use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Unidade;
use Illuminate\Database\Seeder;

class ProductAuxiliarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['sigla' => 'UN', 'descricao' => 'Unidade'],
            ['sigla' => 'PC', 'descricao' => 'Peça'],
            ['sigla' => 'KG', 'descricao' => 'Quilograma'],
            ['sigla' => 'CX', 'descricao' => 'Caixa'],
            ['sigla' => 'LT', 'descricao' => 'Litro'],
            ['sigla' => 'MT', 'descricao' => 'Metro'],
            ['sigla' => 'M2', 'descricao' => 'Metro quadrado'],
            ['sigla' => 'M3', 'descricao' => 'Metro cúbico'],
            ['sigla' => 'PAR', 'descricao' => 'Par'],
            ['sigla' => 'SC', 'descricao' => 'Saco'],
            ['sigla' => 'KIT', 'descricao' => 'Kit'],
        ] as $unidade) {
            Unidade::query()->updateOrCreate(
                ['sigla' => $unidade['sigla']],
                ['descricao' => $unidade['descricao'], 'ativo' => true],
            );
        }

        Grupo::query()->updateOrCreate(
            ['nome' => 'DIVERSOS'],
            ['ativo' => true],
        );

        foreach (['TIROL', 'GENERICO'] as $marca) {
            Marca::query()->updateOrCreate(
                ['nome' => $marca],
                ['ativo' => true],
            );
        }
    }
}
