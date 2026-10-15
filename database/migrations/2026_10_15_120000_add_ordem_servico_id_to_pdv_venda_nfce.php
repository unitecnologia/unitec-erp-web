<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce') || ! Schema::hasTable('ordens_servico')) {
            return;
        }

        if (! Schema::hasColumn('pdv_venda_nfce', 'ordem_servico_id')) {
            Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
                $table->foreignId('ordem_servico_id')
                    ->nullable()
                    ->after('pdv_venda_id')
                    ->constrained('ordens_servico')
                    ->nullOnDelete();
                $table->index(['ordem_servico_id', 'status'], 'pdv_venda_nfce_ordem_servico_status_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pdv_venda_nfce', 'ordem_servico_id')) {
            return;
        }

        Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
            $table->dropIndex('pdv_venda_nfce_ordem_servico_status_index');
            $table->dropConstrainedForeignId('ordem_servico_id');
        });
    }
};
