<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Padroniza as tabelas de preço: VAREJO, ATACADO, ESPECIAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_tables')) {
            return;
        }

        // Renomeia PADRAO → VAREJO (mantém id/código para FKs).
        DB::table('price_tables')
            ->whereRaw('UPPER(descricao) IN (?, ?)', ['PADRAO', 'PADRÃO'])
            ->update([
                'descricao' => 'VAREJO',
                'updated_at' => now(),
            ]);

        $this->ensure('1', 'VAREJO');
        $this->ensure('2', 'ATACADO');
        $this->ensure('3', 'ESPECIAL');
    }

    private function ensure(string $codigo, string $descricao): void
    {
        $row = DB::table('price_tables')
            ->where(function ($q) use ($codigo, $descricao): void {
                $q->whereRaw('UPPER(descricao) = ?', [$descricao])
                    ->orWhere('codigo', $codigo);
            })
            ->orderByRaw('CASE WHEN UPPER(descricao) = ? THEN 0 ELSE 1 END', [$descricao])
            ->first();

        if ($row === null) {
            DB::table('price_tables')->insert([
                'codigo' => $codigo,
                'descricao' => $descricao,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('price_tables')->where('id', $row->id)->update([
            'codigo' => $codigo,
            'descricao' => $descricao,
            'ativo' => true,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_tables')) {
            return;
        }

        DB::table('price_tables')
            ->whereRaw('UPPER(descricao) = ?', ['VAREJO'])
            ->update([
                'descricao' => 'PADRAO',
                'updated_at' => now(),
            ]);
    }
};
