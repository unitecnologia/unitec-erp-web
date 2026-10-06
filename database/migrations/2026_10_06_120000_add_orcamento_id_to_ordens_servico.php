<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordens_servico') || ! Schema::hasTable('orcamentos')) {
            return;
        }

        if (Schema::hasColumn('ordens_servico', 'orcamento_id')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            $table->foreignId('orcamento_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('orcamentos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordens_servico') || ! Schema::hasColumn('ordens_servico', 'orcamento_id')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('orcamento_id');
        });
    }
};
