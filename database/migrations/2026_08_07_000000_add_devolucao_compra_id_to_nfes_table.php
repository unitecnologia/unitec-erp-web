<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfes', function (Blueprint $table): void {
            $table->foreignId('devolucao_compra_id')
                ->nullable()
                ->after('pdv_venda_id')
                ->constrained('devolucoes_compra')
                ->nullOnDelete();

            $table->unique('devolucao_compra_id');
        });
    }

    public function down(): void
    {
        Schema::table('nfes', function (Blueprint $table): void {
            $table->dropUnique(['devolucao_compra_id']);
            $table->dropConstrainedForeignId('devolucao_compra_id');
        });
    }
};
