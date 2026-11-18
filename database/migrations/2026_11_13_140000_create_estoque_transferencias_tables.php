<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferência de estoque — Etapa 1 (interna, mesma empresa).
 * Colunas futuras (NF-e, trânsito, entre CNPJs) já reservadas, sem habilitar o fluxo.
 * Nomes de FK/índice curtos: prefixo unitec_ + limite MySQL 64.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estoque_transferencias')) {
            Schema::create('estoque_transferencias', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_origem_id');
                $table->unsignedBigInteger('empresa_destino_id');
                $table->unsignedBigInteger('estoque_origem_id');
                $table->unsignedBigInteger('estoque_destino_id');
                $table->string('numero', 30);
                $table->string('situacao', 30)->default('rascunho');
                /** interna | entre_cnpjs (Etapa 1 só persiste interna) */
                $table->string('modo', 20)->default('interna');
                $table->date('data');
                $table->string('observacoes', 250)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('confirmado_em')->nullable();
                $table->unsignedBigInteger('confirmado_por')->nullable();
                $table->uuid('idempotencia_token')->nullable();
                /** Preparação Etapa 2+: NF-e / trânsito (não usados na Etapa 1). */
                $table->unsignedBigInteger('nfe_saida_id')->nullable();
                $table->unsignedBigInteger('nfe_entrada_id')->nullable();
                $table->timestamp('enviado_em')->nullable();
                $table->timestamp('recebido_em')->nullable();
                $table->timestamps();

                $table->foreign('empresa_origem_id', 'est_trf_emp_ori_fk')->references('id')->on('empresas')->cascadeOnDelete();
                $table->foreign('empresa_destino_id', 'est_trf_emp_dst_fk')->references('id')->on('empresas')->cascadeOnDelete();
                $table->foreign('estoque_origem_id', 'est_trf_dep_ori_fk')->references('id')->on('estoques')->restrictOnDelete();
                $table->foreign('estoque_destino_id', 'est_trf_dep_dst_fk')->references('id')->on('estoques')->restrictOnDelete();
                $table->foreign('usuario_id', 'est_trf_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('confirmado_por', 'est_trf_conf_user_fk')->references('id')->on('users')->nullOnDelete();

                $table->unique(['empresa_origem_id', 'numero'], 'est_trf_emp_num_uq');
                $table->unique('idempotencia_token', 'est_trf_idem_uq');
                $table->index(['empresa_origem_id', 'situacao', 'data'], 'est_trf_emp_sit_data_idx');
                $table->index(['estoque_origem_id', 'estoque_destino_id'], 'est_trf_deps_idx');
                $table->index('situacao', 'est_trf_sit_idx');
                $table->index('modo', 'est_trf_modo_idx');
                $table->index('data', 'est_trf_data_idx');
                $table->index('nfe_saida_id', 'est_trf_nfe_sai_idx');
                $table->index('nfe_entrada_id', 'est_trf_nfe_ent_idx');
            });
        }

        if (! Schema::hasTable('estoque_transferencia_itens')) {
            Schema::create('estoque_transferencia_itens', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('estoque_transferencia_id');
                $table->unsignedInteger('item');
                $table->unsignedBigInteger('product_id');
                $table->string('produto_codigo', 40)->nullable();
                $table->string('produto_descricao', 200)->nullable();
                $table->decimal('qtd', 12, 3);
                $table->decimal('qtd_disponivel_origem', 12, 3)->nullable();
                $table->timestamps();

                $table->foreign('estoque_transferencia_id', 'est_trf_item_hdr_fk')
                    ->references('id')
                    ->on('estoque_transferencias')
                    ->cascadeOnDelete();
                $table->foreign('product_id', 'est_trf_item_prod_fk')
                    ->references('id')
                    ->on('products')
                    ->restrictOnDelete();

                $table->unique(['estoque_transferencia_id', 'item'], 'est_trf_item_seq_uq');
                $table->index('product_id', 'est_trf_item_prod_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_transferencia_itens');
        Schema::dropIfExists('estoque_transferencias');
    }
};
