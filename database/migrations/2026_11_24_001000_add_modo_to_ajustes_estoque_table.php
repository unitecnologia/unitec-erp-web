<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste o modo informado na tela (somar|substituir).
 * Histórico antigo permanece NULL → relatório exibe "Não identificado".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ajustes_estoque')) {
            return;
        }

        if (Schema::hasColumn('ajustes_estoque', 'modo')) {
            return;
        }

        Schema::table('ajustes_estoque', function (Blueprint $table): void {
            $table->string('modo', 20)->nullable()->after('qtd_ajust');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ajustes_estoque') || ! Schema::hasColumn('ajustes_estoque', 'modo')) {
            return;
        }

        Schema::table('ajustes_estoque', function (Blueprint $table): void {
            $table->dropColumn('modo');
        });
    }
};
