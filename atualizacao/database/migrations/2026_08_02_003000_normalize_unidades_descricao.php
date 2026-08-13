<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Padroniza descrições de unidade (Title Case + acentos) para o select do produto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unidades')) {
            return;
        }

        $map = [
            'UN' => 'Unidade',
            'PC' => 'Peça',
            'KG' => 'Quilograma',
            'CX' => 'Caixa',
            'LT' => 'Litro',
            'MT' => 'Metro',
            'M2' => 'Metro quadrado',
            'M3' => 'Metro cúbico',
            'PAR' => 'Par',
            'SC' => 'Saco',
            'KIT' => 'Kit',
            'G' => 'Grama',
            'ML' => 'Mililitro',
            'DZ' => 'Dúzia',
            'FD' => 'Fardo',
            'PCT' => 'Pacote',
            'RL' => 'Rolo',
            'CJ' => 'Conjunto',
        ];

        foreach ($map as $sigla => $descricao) {
            DB::table('unidades')
                ->whereRaw('UPPER(TRIM(sigla)) = ?', [$sigla])
                ->update(['descricao' => $descricao]);
        }
    }

    public function down(): void
    {
        // Mantém Title Case; sem rollback para ALL CAPS.
    }
};
