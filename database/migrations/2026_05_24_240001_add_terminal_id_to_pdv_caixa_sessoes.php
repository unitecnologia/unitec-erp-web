<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_caixa_sessoes', function (Blueprint $table) {
            if (! Schema::hasColumn('pdv_caixa_sessoes', 'terminal_id')) {
                $table->foreignId('terminal_id')
                    ->nullable()
                    ->after('empresa_id')
                    ->constrained('terminais')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_caixa_sessoes', function (Blueprint $table) {
            if (Schema::hasColumn('pdv_caixa_sessoes', 'terminal_id')) {
                $table->dropConstrainedForeignId('terminal_id');
            }
        });
    }
};
