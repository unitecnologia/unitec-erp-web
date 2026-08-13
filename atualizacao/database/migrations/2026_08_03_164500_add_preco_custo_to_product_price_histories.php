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
            if (! Schema::hasColumn('product_price_histories', 'preco_custo')) {
                $table->decimal('preco_custo', 12, 2)->nullable()->after('ultimo_preco');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_histories')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            if (Schema::hasColumn('product_price_histories', 'preco_custo')) {
                $table->dropColumn('preco_custo');
            }
        });
    }
};
