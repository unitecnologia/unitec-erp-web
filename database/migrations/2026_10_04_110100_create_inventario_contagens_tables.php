<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_contagens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('estoque_id')->constrained('estoques');
            $table->foreignId('user_id')->constrained('users');
            $table->date('data');
            $table->string('status', 20)->default('rascunho');
            $table->timestamp('finalizada_em')->nullable();
            $table->foreignId('finalizada_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'estoque_id', 'user_id', 'status'], 'inventario_contagens_escopo_idx');
        });

        Schema::create('inventario_contagem_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventario_contagem_id')->constrained('inventario_contagens')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('codigo', 60)->default('');
            $table->string('descricao')->default('');
            $table->string('unidade', 20)->default('UN');
            $table->decimal('quantidade_contada', 12, 3);
            $table->decimal('saldo_referencia', 12, 3);
            $table->unsignedBigInteger('movimento_referencia_id')->default(0);
            $table->decimal('diferenca', 12, 3)->nullable();
            $table->foreignId('ajuste_estoque_id')->nullable()->constrained('ajustes_estoque')->nullOnDelete();
            $table->string('situacao', 20)->default('contado');
            $table->timestamps();

            $table->unique(['inventario_contagem_id', 'product_id'], 'inventario_item_produto_uq');
            $table->index(['inventario_contagem_id', 'situacao'], 'inventario_item_situacao_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_contagem_itens');
        Schema::dropIfExists('inventario_contagens');
    }
};
