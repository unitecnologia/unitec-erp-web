<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        if (Schema::hasColumn('estoque_movimentacoes', 'origem_numero')) {
            return;
        }

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            $table->string('origem_numero', 40)->nullable()->after('origem_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        if (! Schema::hasColumn('estoque_movimentacoes', 'origem_numero')) {
            return;
        }

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            $table->dropColumn('origem_numero');
        });
    }
};
