<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OS: tabela de preço da sessão + flag de preço manual nos itens.
 * Não altera estoque/financeiro/faturamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordens_servico') && ! Schema::hasColumn('ordens_servico', 'price_table_id')) {
            Schema::table('ordens_servico', function (Blueprint $table): void {
                $table->foreignId('price_table_id')
                    ->nullable()
                    ->after('atendente_id')
                    ->constrained('price_tables')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('ordem_servico_itens') && ! Schema::hasColumn('ordem_servico_itens', 'preco_manual')) {
            Schema::table('ordem_servico_itens', function (Blueprint $table): void {
                $table->boolean('preco_manual')->default(false)->after('preco');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ordens_servico') && Schema::hasColumn('ordens_servico', 'price_table_id')) {
            Schema::table('ordens_servico', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('price_table_id');
            });
        }

        if (Schema::hasTable('ordem_servico_itens') && Schema::hasColumn('ordem_servico_itens', 'preco_manual')) {
            Schema::table('ordem_servico_itens', function (Blueprint $table): void {
                $table->dropColumn('preco_manual');
            });
        }
    }
};
