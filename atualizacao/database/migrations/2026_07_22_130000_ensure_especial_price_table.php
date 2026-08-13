<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garante a tabela ESPECIAL (nível de preço do produto) para seleção
 * em Colaboradores → Tabela Venda, alinhada a VAREJO/ATACADO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_tables')) {
            return;
        }

        $exists = DB::table('price_tables')
            ->where(function ($q): void {
                $q->whereRaw('UPPER(descricao) = ?', ['ESPECIAL'])
                    ->orWhere('codigo', '3');
            })
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('price_tables')->insert([
            'codigo' => '3',
            'descricao' => 'ESPECIAL',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_tables')) {
            return;
        }

        DB::table('price_tables')
            ->where('codigo', '3')
            ->whereRaw('UPPER(descricao) = ?', ['ESPECIAL'])
            ->delete();
    }
};
