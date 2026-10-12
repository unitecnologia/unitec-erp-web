<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaura products.comissao_pct para a comissão adicional da Precificação
     * (usada quando products.paga_comissao = true).
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'comissao_pct')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('comissao_pct', 8, 2)->default(0)->after('preco_especial');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'comissao_pct')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('comissao_pct');
        });
    }
};
