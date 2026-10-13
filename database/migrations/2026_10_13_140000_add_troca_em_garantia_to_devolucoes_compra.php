<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devolucoes_compra', 'troca_em_garantia')) {
            Schema::table('devolucoes_compra', function (Blueprint $table): void {
                $table->boolean('troca_em_garantia')->default(false)->after('observacoes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devolucoes_compra', 'troca_em_garantia')) {
            Schema::table('devolucoes_compra', function (Blueprint $table): void {
                $table->dropColumn('troca_em_garantia');
            });
        }
    }
};
