<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C1 — Fundação da Conciliação Bancária (somente estrutura).
 * Não gera lançamentos, baixas nem altera tabelas financeiras existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extrato_importacoes')) {
            Schema::create('extrato_importacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('caixa_conta_id')->index();

                $table->string('arquivo_nome', 255);
                $table->char('arquivo_sha256', 64);
                $table->string('formato', 16)->default('ofx');

                // pendente | processando | concluida | erro | cancelada
                $table->string('status', 20)->default('pendente')->index();

                $table->date('periodo_inicio')->nullable();
                $table->date('periodo_fim')->nullable();

                $table->unsignedInteger('total_linhas')->default(0);
                $table->decimal('total_creditos', 14, 2)->default(0);
                $table->decimal('total_debitos', 14, 2)->default(0);

                $table->text('mensagem_erro')->nullable();
                $table->unsignedBigInteger('imported_by_user_id')->nullable()->index();
                $table->timestamp('imported_at')->nullable();

                $table->timestamps();

                // Mesmo arquivo na mesma conta/empresa não reimporta.
                $table->unique(
                    ['empresa_id', 'caixa_conta_id', 'arquivo_sha256'],
                    'extrato_imp_emp_conta_sha_uq'
                );

                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'status'],
                    'extrato_imp_emp_conta_status_idx'
                );
            });
        }

        if (! Schema::hasTable('extrato_lancamentos')) {
            Schema::create('extrato_lancamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('caixa_conta_id')->index();
                $table->unsignedBigInteger('extrato_importacao_id')->index();

                $table->date('data_movimento')->index();
                $table->date('data_posted')->nullable();

                // credito | debito | outro
                $table->string('tipo', 16)->default('outro');
                $table->decimal('valor', 14, 2);

                $table->string('historico', 500)->nullable();
                $table->string('memo', 500)->nullable();
                $table->string('payee', 255)->nullable();
                $table->string('check_num', 40)->nullable();

                // Identificador bancário (FITID OFX). Pode ser nulo em arquivos pobres.
                $table->string('fitid', 120)->nullable();

                // Sempre preenchido: FITID normalizado OU hash estável (data|tipo|valor|historico|seq).
                $table->char('fingerprint', 64);

                // pendente | conciliado | ignorado
                $table->string('situacao', 20)->default('pendente')->index();
                $table->timestamp('conciliado_em')->nullable();
                $table->unsignedBigInteger('conciliado_por_user_id')->nullable();

                // Vínculo futuro (C3+): origem polimórfica leve + opcional Livro Caixa.
                $table->string('origem_tipo', 40)->nullable();
                $table->unsignedBigInteger('origem_id')->nullable();
                $table->unsignedBigInteger('caixa_lancamento_id')->nullable()->index();

                $table->timestamps();

                // Anti-duplicidade de linha (com ou sem FITID confiável).
                $table->unique(
                    ['empresa_id', 'caixa_conta_id', 'fingerprint'],
                    'extrato_lanc_emp_conta_fp_uq'
                );

                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'data_movimento'],
                    'extrato_lanc_emp_conta_data_idx'
                );

                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'situacao', 'data_movimento'],
                    'extrato_lanc_emp_conta_sit_data_idx'
                );

                $table->index(
                    ['origem_tipo', 'origem_id'],
                    'extrato_lanc_origem_idx'
                );

                // FITID quando existir: busca rápida (unicidade efetiva via fingerprint).
                $table->index(
                    ['empresa_id', 'caixa_conta_id', 'fitid'],
                    'extrato_lanc_emp_conta_fitid_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extrato_lancamentos');
        Schema::dropIfExists('extrato_importacoes');
    }
};
