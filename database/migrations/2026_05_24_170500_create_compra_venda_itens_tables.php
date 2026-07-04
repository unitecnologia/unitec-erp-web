<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['product_id', 'compra_id']);
        });

        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_item', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['product_id', 'venda_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('compra_itens');
    }
};
