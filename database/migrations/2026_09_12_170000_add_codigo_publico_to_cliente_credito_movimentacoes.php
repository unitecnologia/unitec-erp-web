<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliente_credito_movimentacoes')) {
            return;
        }

        if (! Schema::hasColumn('cliente_credito_movimentacoes', 'codigo_publico')) {
            Schema::table('cliente_credito_movimentacoes', function (Blueprint $table): void {
                $table->string('codigo_publico', 20)->nullable();
                $table->unique('codigo_publico', 'cli_cred_mov_codigo_publico_unq');
            });
        }

        // Tipo oficial: credito | debito. Estorno é o vínculo (estorna_id), não um terceiro tipo.
        DB::table('cliente_credito_movimentacoes')
            ->where('tipo', 'uso')
            ->update(['tipo' => 'debito']);

        DB::table('cliente_credito_movimentacoes')
            ->where('tipo', 'estorno')
            ->where('sinal', '<', 0)
            ->update(['tipo' => 'debito']);

        DB::table('cliente_credito_movimentacoes')
            ->where('tipo', 'estorno')
            ->where('sinal', '>=', 0)
            ->update(['tipo' => 'credito']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cliente_credito_movimentacoes')) {
            return;
        }

        Schema::table('cliente_credito_movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('cliente_credito_movimentacoes', 'codigo_publico')) {
                $table->dropUnique('cli_cred_mov_codigo_publico_unq');
                $table->dropColumn('codigo_publico');
            }
        });
    }
};
