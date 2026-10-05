<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inventario_contagem_itens', 'movimento_referencia_id')) {
            return;
        }

        Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
            $table->unsignedBigInteger('movimento_referencia_id')->default(0)->after('saldo_referencia');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventario_contagem_itens', 'movimento_referencia_id')) {
            return;
        }

        Schema::table('inventario_contagem_itens', function (Blueprint $table): void {
            $table->dropColumn('movimento_referencia_id');
        });
    }
};
