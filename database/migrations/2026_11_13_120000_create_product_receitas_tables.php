<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receitas de fabricação (independentes de is_composicao / product_compositions).
 *
 * Alcance: receita global por produto acabado (cadastro compartilhado entre empresas).
 * Consumo futuro de estoque permanece isolado por empresa/depósito na etapa de fabricar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_receitas')) {
            Schema::create('product_receitas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qtd_produzida', 12, 3)->default(1);
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('product_id', 'unitec_prod_receita_product_unique');
            });
        }

        if (! Schema::hasTable('product_receita_itens')) {
            Schema::create('product_receita_itens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('receita_id')->constrained('product_receitas')->cascadeOnDelete();
                $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('quantidade', 12, 3)->default(1);
                $table->timestamps();

                $table->unique(['receita_id', 'component_product_id'], 'unitec_prod_receita_item_unique');
                $table->index('component_product_id', 'unitec_prod_receita_item_comp_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_receita_itens');
        Schema::dropIfExists('product_receitas');
    }
};
