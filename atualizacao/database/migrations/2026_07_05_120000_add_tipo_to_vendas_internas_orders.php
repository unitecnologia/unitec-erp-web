<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_internas_orders', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('orcamento')->after('vendedor_id');
            $table->unsignedBigInteger('forca_vendas_order_id')->nullable()->after('orcamento_id');

            $table->index('tipo');
            $table->index('forca_vendas_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_internas_orders', function (Blueprint $table): void {
            $table->dropIndex(['tipo']);
            $table->dropIndex(['forca_vendas_order_id']);
            $table->dropColumn(['tipo', 'forca_vendas_order_id']);
        });
    }
};
