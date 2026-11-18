<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intenção SuperTEF: criada antes do POST; nunca reenvia cobrança automaticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tef_intencoes')) {
            return;
        }

        Schema::create('tef_intencoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            $table->unsignedBigInteger('caixa_sessao_id')->nullable()->index();
            $table->unsignedBigInteger('pdv_venda_id')->nullable()->index();
            $table->unsignedBigInteger('pdv_venda_pagamento_id')->nullable();

            $table->string('order_id', 64)->unique();
            $table->unsignedBigInteger('payment_uniqueid')->nullable()->index();
            $table->unsignedInteger('pos_id');
            $table->decimal('amount', 12, 2);
            $table->string('transaction_type', 8)->default('1');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->unsignedTinyInteger('installment_type')->default(1);

            // criada | post_enviado | pendente | paga | cancelada | estornada | falha | indeterminada
            $table->string('status', 24)->default('criada')->index();
            $table->boolean('post_enviado')->default(false);
            $table->timestamp('post_enviado_em')->nullable();
            $table->timestamp('last_poll_at')->nullable();
            $table->timestamp('pago_em')->nullable();

            $table->string('nsu', 40)->nullable();
            $table->string('autorizacao', 40)->nullable();
            $table->string('bandeira', 40)->nullable();
            $table->string('payment_message', 191)->nullable();
            $table->json('raw_last')->nullable();

            $table->timestamps();

            $table->index(['terminal_id', 'pos_id', 'status'], 'tef_intencoes_pos_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tef_intencoes');
    }
};
