<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('promo_data_inicio')->nullable()->after('mostrar_no_app');
            $table->date('promo_data_fim')->nullable()->after('promo_data_inicio');
            $table->decimal('promo_preco_venda', 12, 2)->default(0)->after('promo_data_fim');
            $table->decimal('promo_preco_atacado', 12, 2)->default(0)->after('promo_preco_venda');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'promo_data_inicio',
                'promo_data_fim',
                'promo_preco_venda',
                'promo_preco_atacado',
            ]);
        });
    }
};
