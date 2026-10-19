<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_compositions')) {
            return;
        }

        if (Schema::hasColumn('product_compositions', 'is_adicional')) {
            return;
        }

        Schema::table('product_compositions', function (Blueprint $table) {
            $table->boolean('is_adicional')->default(false)->after('total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_compositions')) {
            return;
        }

        if (! Schema::hasColumn('product_compositions', 'is_adicional')) {
            return;
        }

        Schema::table('product_compositions', function (Blueprint $table) {
            $table->dropColumn('is_adicional');
        });
    }
};
