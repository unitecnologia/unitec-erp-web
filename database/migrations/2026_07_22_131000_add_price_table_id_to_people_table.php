<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('people') || ! Schema::hasTable('price_tables')) {
            return;
        }

        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'price_table_id')) {
                $table->foreignId('price_table_id')
                    ->nullable()
                    ->after('tabela_prazo_id')
                    ->constrained('price_tables')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('people') || ! Schema::hasColumn('people', 'price_table_id')) {
            return;
        }

        Schema::table('people', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_table_id');
        });
    }
};
