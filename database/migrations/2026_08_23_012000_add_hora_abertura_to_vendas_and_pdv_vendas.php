<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdv_vendas') && ! Schema::hasColumn('pdv_vendas', 'aberto_em')) {
            Schema::table('pdv_vendas', function (Blueprint $table): void {
                $table->timestamp('aberto_em')->nullable()->after('fechado_em');
            });
        }

        if (Schema::hasTable('vendas') && ! Schema::hasColumn('vendas', 'hora_abertura')) {
            Schema::table('vendas', function (Blueprint $table): void {
                $table->time('hora_abertura')->nullable()->after('hora');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas') && Schema::hasColumn('vendas', 'hora_abertura')) {
            Schema::table('vendas', function (Blueprint $table): void {
                $table->dropColumn('hora_abertura');
            });
        }

        if (Schema::hasTable('pdv_vendas') && Schema::hasColumn('pdv_vendas', 'aberto_em')) {
            Schema::table('pdv_vendas', function (Blueprint $table): void {
                $table->dropColumn('aberto_em');
            });
        }
    }
};
