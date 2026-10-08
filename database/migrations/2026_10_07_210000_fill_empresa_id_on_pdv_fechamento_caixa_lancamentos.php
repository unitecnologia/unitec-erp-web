<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamentos de caixa PDV eram lançados sem empresa_id e não apareciam no Caixa
 * (a listagem filtra pela empresa atual). Só preenche empresa_id; valores intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('caixa_lancamentos')
            || ! Schema::hasColumn('caixa_lancamentos', 'empresa_id')
            || ! Schema::hasTable('pdv_caixa_sessoes')
            || ! Schema::hasColumn('pdv_caixa_sessoes', 'empresa_id')
        ) {
            return;
        }

        DB::table('caixa_lancamentos')
            ->whereNull('empresa_id')
            ->where('documento', 'like', 'PDV-CX-%')
            ->orderBy('id')
            ->get(['id', 'documento'])
            ->each(function (object $lancamento): void {
                if (! preg_match('/^PDV-CX-(\d+)/', (string) $lancamento->documento, $m)) {
                    return;
                }

                $empresaId = DB::table('pdv_caixa_sessoes')->where('id', (int) $m[1])->value('empresa_id');

                if ($empresaId) {
                    DB::table('caixa_lancamentos')
                        ->where('id', $lancamento->id)
                        ->update(['empresa_id' => (int) $empresaId]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
