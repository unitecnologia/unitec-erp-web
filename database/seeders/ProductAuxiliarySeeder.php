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
            ['sigla' => 'UN', 'descricao' => 'UNIDADE'],
            ['sigla' => 'PC', 'descricao' => 'PC'],
            ['sigla' => 'KG', 'descricao' => 'QUILOGRAMA'],
            ['sigla' => 'CX', 'descricao' => 'CAIXA'],
            ['sigla' => 'LT', 'descricao' => 'LITRO'],
            ['sigla' => 'MT', 'descricao' => 'METRO'],
            ['sigla' => 'M2', 'descricao' => 'METRO QUADRADO'],
            ['sigla' => 'M3', 'descricao' => 'METRO CUBICO'],
            ['sigla' => 'PAR', 'descricao' => 'PAR'],
            ['sigla' => 'SC', 'descricao' => 'SACO'],
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
