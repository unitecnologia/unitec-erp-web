<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forca_vendas_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('forca_vendas_orders', 'meli_order_id')) {
                $table->string('meli_order_id', 32)->nullable()->after('uuid');
                $table->unique('meli_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forca_vendas_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('forca_vendas_orders', 'meli_order_id')) {
                $table->dropUnique(['meli_order_id']);
                $table->dropColumn('meli_order_id');
            }
        });
    }
};
