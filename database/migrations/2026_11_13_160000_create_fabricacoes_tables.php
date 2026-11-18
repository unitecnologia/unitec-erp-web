<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico técnico de fabricação (Etapa 2).
 * Estorno (Etapa 3) reutilizará status + snapshot; não apagar linhas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fabricacoes')) {
            Schema::create('fabricacoes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('numero', 20);
                $table->dateTime('data_hora');
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('quantidade', 12, 3);
                $table->foreignId('estoque_id')->constrained('estoques')->restrictOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('custo_total', 14, 2)->default(0);
                $table->decimal('custo_unitario', 14, 2)->default(0);
                $table->string('status', 20)->default('finalizada');
                $table->string('idempotencia_token', 64);
                $table->decimal('rendimento_receita', 12, 3)->nullable();
                $table->decimal('fator', 18, 8)->nullable();
                $table->string('observacao', 500)->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero'], 'unitec_fab_empresa_numero_unique');
                $table->unique('idempotencia_token', 'unitec_fab_idempotencia_unique');
                $table->index(['empresa_id', 'data_hora'], 'unitec_fab_empresa_data_idx');
                $table->index(['product_id', 'empresa_id'], 'unitec_fab_produto_empresa_idx');
                $table->index('status', 'unitec_fab_status_idx');
            });
        }

        if (! Schema::hasTable('fabricacao_itens')) {
            Schema::create('fabricacao_itens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fabricacao_id')->constrained('fabricacoes')->cascadeOnDelete();
                $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
                $table->string('produto_codigo', 40)->nullable();
                $table->string('produto_descricao', 180)->nullable();
                $table->string('unidade', 10)->nullable();
                $table->decimal('quantidade_consumida', 12, 3);
                $table->decimal('custo_unitario', 14, 2)->default(0);
                $table->decimal('custo_total', 14, 2)->default(0);
                $table->timestamps();

                $table->index('fabricacao_id', 'unitec_fab_item_fab_idx');
                $table->index('component_product_id', 'unitec_fab_item_comp_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fabricacao_itens');
        Schema::dropIfExists('fabricacoes');
    }
};
