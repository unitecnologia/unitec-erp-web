<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordens_servico') || ! Schema::hasTable('vendas')) {
            return;
        }

        if (Schema::hasColumn('ordens_servico', 'venda_id')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            $table->foreignId('venda_id')
                ->nullable()
                ->after('orcamento_id')
                ->constrained('vendas')
                ->nullOnDelete();

            $table->unique('venda_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordens_servico') || ! Schema::hasColumn('ordens_servico', 'venda_id')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            $table->dropUnique(['venda_id']);
            $table->dropConstrainedForeignId('venda_id');
        });
    }
};
