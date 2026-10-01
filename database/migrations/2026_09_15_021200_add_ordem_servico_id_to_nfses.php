<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfses')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfses', 'ordem_servico_id')) {
                $table->foreignId('ordem_servico_id')
                    ->nullable()
                    ->after('empresa_id')
                    ->constrained('ordens_servico')
                    ->nullOnDelete();
                $table->index(['ordem_servico_id', 'status'], 'nfses_ordem_servico_status_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfses')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            if (Schema::hasColumn('nfses', 'ordem_servico_id')) {
                $table->dropForeign(['ordem_servico_id']);
                $table->dropIndex('nfses_ordem_servico_status_index');
                $table->dropColumn('ordem_servico_id');
            }
        });
    }
};
