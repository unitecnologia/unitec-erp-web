<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->decimal('valor_abertura', 12, 2)->default(0);
            $table->decimal('valor_fechamento', 12, 2)->nullable();
            $table->timestamp('aberto_em');
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fechado_em']);
        });

        Schema::create('pdv_vendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_caixa_sessao_id')->constrained('pdv_caixa_sessoes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->decimal('total', 12, 2);
            $table->string('forma_pagamento');
            $table->boolean('fiscal')->default(false);
            $table->timestamps();

            $table->unique(['pdv_caixa_sessao_id', 'numero']);
        });

        Schema::create('pdv_venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_venda_id')->constrained('pdv_vendas')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('codigo')->nullable();
            $table->string('descricao');
            $table->string('unidade', 10)->default('UN');
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('pdv_caixa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_caixa_sessao_id')->constrained('pdv_caixa_sessoes')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('historico');
            $table->string('forma_pagamento')->nullable();
            $table->decimal('entrada', 12, 2)->default(0);
            $table->decimal('saida', 12, 2)->default(0);
            $table->foreignId('pdv_venda_id')->nullable()->constrained('pdv_vendas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_caixa_movimentos');
        Schema::dropIfExists('pdv_venda_itens');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('pdv_caixa_sessoes');
    }
};
