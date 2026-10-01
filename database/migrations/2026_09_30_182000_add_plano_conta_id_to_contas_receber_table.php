<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contas_receber') && ! Schema::hasColumn('contas_receber', 'plano_conta_id')) {
            Schema::table('contas_receber', function (Blueprint $table): void {
                $table->foreignId('plano_conta_id')
                    ->nullable()
                    ->constrained('planos_contas')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contas_receber') && Schema::hasColumn('contas_receber', 'plano_conta_id')) {
            Schema::table('contas_receber', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('plano_conta_id');
            });
        }
    }
};
