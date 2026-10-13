<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfes', function (Blueprint $table): void {
            // O UNIQUE atual sustenta a FK; precisa dropar a FK antes de trocar o índice.
            $table->dropForeign(['devolucao_compra_id']);
            $table->dropUnique(['devolucao_compra_id']);
            $table->index('devolucao_compra_id', 'nfes_devolucao_compra_id_index');
            $table->foreign('devolucao_compra_id')
                ->references('id')
                ->on('devolucoes_compra')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nfes', function (Blueprint $table): void {
            $table->dropForeign(['devolucao_compra_id']);
            $table->dropIndex('nfes_devolucao_compra_id_index');
            $table->unique('devolucao_compra_id');
            $table->foreign('devolucao_compra_id')
                ->references('id')
                ->on('devolucoes_compra')
                ->nullOnDelete();
        });
    }
};
