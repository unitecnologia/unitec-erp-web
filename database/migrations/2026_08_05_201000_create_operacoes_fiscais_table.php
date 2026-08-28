<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes_fiscais', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->unique();
            $table->unsignedInteger('cfop_financeiro_estadual')->nullable();
            $table->unsignedInteger('cfop_financeiro_interestadual')->nullable();
            $table->unsignedInteger('cfop_acompanhamento_estadual')->nullable();
            $table->unsignedInteger('cfop_acompanhamento_interestadual')->nullable();
            $table->unsignedInteger('cfop_devolucao_vendas_estadual')->nullable();
            $table->unsignedInteger('cfop_devolucao_vendas_interestadual')->nullable();
            $table->unsignedInteger('cfop_devolucao_compras_estadual')->nullable();
            $table->unsignedInteger('cfop_devolucao_compras_interestadual')->nullable();
            $table->unsignedInteger('cfop_transferencias_estadual')->nullable();
            $table->unsignedInteger('cfop_transferencias_interestadual')->nullable();
            $table->unsignedInteger('cfop_outras_saidas_estadual')->nullable();
            $table->unsignedInteger('cfop_outras_saidas_interestadual')->nullable();
            $table->unsignedInteger('cfop_entrada_futura_estadual')->nullable();
            $table->unsignedInteger('cfop_entrada_futura_interestadual')->nullable();
            $table->unsignedInteger('cfop_bonificacao_estadual')->nullable();
            $table->unsignedInteger('cfop_bonificacao_interestadual')->nullable();
            $table->unsignedInteger('cfop_saida_perda_estadual')->nullable();
            $table->unsignedInteger('cfop_saida_perda_interestadual')->nullable();
            $table->text('mensagem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacoes_fiscais');
    }
};
