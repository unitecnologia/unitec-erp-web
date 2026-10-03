<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_histories') || Schema::hasColumn('product_price_histories', 'compra_id')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->foreignId('compra_id')->nullable()->after('forma_alteracao')->constrained('compras')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_histories') || ! Schema::hasColumn('product_price_histories', 'compra_id')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('compra_id');
        });
    }
};
