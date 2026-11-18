<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C5B.1 — Destino bancário previsto das liquidações SuperTEF.
 * Não gera liquidação, CR, caixa nem altera PDV/TEF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tef_liquidacao_destinos')) {
            return;
        }

        Schema::create('tef_liquidacao_destinos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            // '' = configuração padrão do terminal (sem CNPJ de adquirente).
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['empresa_id', 'terminal_id', 'acquirer_cnpj'],
                'tef_liq_dest_emp_term_acq_uq'
            );
            $table->index(
                ['empresa_id', 'terminal_id', 'ativo'],
                'tef_liq_dest_emp_term_ativo_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tef_liquidacao_destinos');
    }
};
