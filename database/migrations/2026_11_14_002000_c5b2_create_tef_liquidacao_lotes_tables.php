<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C5B.2 — Controle de lotes de liquidação SuperTEF.
 * Somente registro de controle; não gera movimento financeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_liquidacao_lotes')) {
            Schema::create('tef_liquidacao_lotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('terminal_id')->index();
                // '' = adquirente não identificada / padrão do terminal.
                $table->string('acquirer_cnpj', 14)->default('');
                $table->unsignedBigInteger('caixa_conta_id')->index();
                $table->date('periodo_de')->nullable();
                $table->date('periodo_ate')->nullable();
                $table->date('data_prevista_liquidacao')->nullable();
                $table->decimal('valor_bruto', 15, 2)->default(0);
                $table->decimal('valor_taxas', 15, 2)->default(0);
                // nao_informada | informada | apurada (reservado; C5B.2 não inventa apuração)
                $table->string('taxa_origem', 24)->default('nao_informada');
                $table->decimal('valor_liquido', 15, 2)->default(0);
                // preparacao | aguardando | conferido | cancelado
                $table->string('situacao', 24)->default('preparacao')->index();
                $table->string('identificador_externo', 80)->nullable();
                $table->string('origem', 40)->default('manual');
                $table->string('observacao', 500)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();

                $table->index(
                    ['empresa_id', 'terminal_id', 'situacao'],
                    'tef_liq_lote_emp_term_sit_idx'
                );
                $table->index(
                    ['empresa_id', 'periodo_de', 'periodo_ate'],
                    'tef_liq_lote_emp_periodo_idx'
                );
            });
        }

        if (! Schema::hasTable('tef_liquidacao_lote_itens')) {
            Schema::create('tef_liquidacao_lote_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lote_id')->index();
                $table->unsignedBigInteger('tef_intencao_id')->index();
                $table->unsignedBigInteger('pdv_venda_id')->nullable()->index();
                $table->decimal('valor_bruto', 15, 2);
                $table->decimal('valor_taxa', 15, 2)->default(0);
                $table->decimal('valor_liquido', 15, 2);
                $table->string('nsu', 40)->nullable();
                $table->string('autorizacao', 40)->nullable();
                $table->string('bandeira', 40)->nullable();
                $table->string('transaction_type', 8)->nullable();
                $table->unsignedTinyInteger('installment_count')->default(1);
                $table->unsignedTinyInteger('parcela_numero')->default(1);
                $table->string('acquirer_cnpj', 14)->nullable();
                $table->timestamp('pago_em')->nullable();
                $table->date('data_prevista_parcela')->nullable();
                $table->timestamps();

                // Duplicidade em lotes ativos é validada no service (lote cancelado libera a intenção).
                $table->index(
                    ['tef_intencao_id', 'parcela_numero'],
                    'tef_liq_item_intencao_parcela_idx'
                );
                $table->index(['lote_id', 'tef_intencao_id'], 'tef_liq_item_lote_int_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tef_liquidacao_lote_itens');
        Schema::dropIfExists('tef_liquidacao_lotes');
    }
};
