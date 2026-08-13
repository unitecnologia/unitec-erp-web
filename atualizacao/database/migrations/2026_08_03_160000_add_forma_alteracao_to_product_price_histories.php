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
            if (! Schema::hasColumn('product_price_histories', 'forma_alteracao')) {
                $table->string('forma_alteracao', 20)->nullable()->after('usuario');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_histories')) {
            return;
        }

        Schema::table('product_price_histories', function (Blueprint $table): void {
            if (Schema::hasColumn('product_price_histories', 'forma_alteracao')) {
                $table->dropColumn('forma_alteracao');
            }
        });
    }
};
