<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caixa do colaborador por empresa (pivot empresa_vendedor).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_vendedor')) {
            return;
        }

        Schema::table('empresa_vendedor', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresa_vendedor', 'caixa_conta_id')) {
                $table->foreignId('caixa_conta_id')
                    ->nullable()
                    ->after('empresa_id')
                    ->constrained('caixa_contas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresa_vendedor') || ! Schema::hasColumn('empresa_vendedor', 'caixa_conta_id')) {
            return;
        }

        Schema::table('empresa_vendedor', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('caixa_conta_id');
        });
    }
};
