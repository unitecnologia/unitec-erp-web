<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_histories')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_price_histories', 'preco_atacado')) {
                $table->decimal('preco_atacado', 12, 2)->nullable()->after('ultimo_preco');
            }
            if (! Schema::hasColumn('product_price_histories', 'preco_especial')) {
                $table->decimal('preco_especial', 12, 2)->nullable()->after('preco_atacado');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_histories')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            if (Schema::hasColumn('product_price_histories', 'preco_especial')) {
                $table->dropColumn('preco_especial');
            }
            if (Schema::hasColumn('product_price_histories', 'preco_atacado')) {
                $table->dropColumn('preco_atacado');
            }
        });
    }
};
