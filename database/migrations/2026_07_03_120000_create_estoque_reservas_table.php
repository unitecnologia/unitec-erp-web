<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->foreignId('forca_vendas_order_id')->nullable()->constrained('forca_vendas_orders')->cascadeOnDelete();
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->unsignedBigInteger('orcamento_item_id')->nullable();
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->string('vendedor_nome')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('plataforma', 20)->default('mobile');
            $table->string('cliente_nome')->nullable();
            $table->string('pedido_numero', 40)->nullable();
            $table->string('status', 20)->default('ativa');
            $table->timestamp('consumida_at')->nullable();
            $table->timestamp('liberada_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['forca_vendas_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_reservas');
    }
};
