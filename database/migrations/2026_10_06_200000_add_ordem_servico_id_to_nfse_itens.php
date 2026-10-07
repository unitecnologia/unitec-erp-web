<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_itens') || Schema::hasColumn('nfse_itens', 'ordem_servico_id')) {
            return;
        }

        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->foreignId('ordem_servico_id')
                ->nullable()
                ->after('nfse_id')
                ->constrained('ordens_servico')
                ->nullOnDelete();
        });

        if (! Schema::hasColumn('nfses', 'ordem_servico_id')) {
            return;
        }

        $prefixo = DB::getTablePrefix();

        DB::table('nfse_itens')
            ->whereNull('ordem_servico_id')
            ->update([
                'ordem_servico_id' => DB::raw(
                    '(select n.ordem_servico_id from '.$prefixo.'nfses n where n.id = '.$prefixo.'nfse_itens.nfse_id)'
                ),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfse_itens') || ! Schema::hasColumn('nfse_itens', 'ordem_servico_id')) {
            return;
        }

        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ordem_servico_id');
        });
    }
};
