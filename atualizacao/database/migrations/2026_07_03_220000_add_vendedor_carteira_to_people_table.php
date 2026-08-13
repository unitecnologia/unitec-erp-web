<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'vendedor_fv_id')) {
                $table->foreignId('vendedor_fv_id')
                    ->nullable()
                    ->after('tabela_prazo_id')
                    ->constrained('vendedores')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('people', 'vendedor_loja_id')) {
                $table->foreignId('vendedor_loja_id')
                    ->nullable()
                    ->after('vendedor_fv_id')
                    ->constrained('vendedores')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (Schema::hasColumn('people', 'vendedor_loja_id')) {
                $table->dropConstrainedForeignId('vendedor_loja_id');
            }
            if (Schema::hasColumn('people', 'vendedor_fv_id')) {
                $table->dropConstrainedForeignId('vendedor_fv_id');
            }
        });
    }
};
