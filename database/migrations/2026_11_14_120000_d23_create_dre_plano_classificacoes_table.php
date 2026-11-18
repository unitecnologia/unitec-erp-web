<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2.3 — Mapeamento isolado plano → classificação DRE (por empresa).
 * Não altera planos_contas nem reclassifica histórico automaticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dre_plano_classificacoes')) {
            return;
        }

        Schema::create('dre_plano_classificacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('plano_conta_id')->index();
            // Ver DrePlanoClassificacaoCatalog — valores estáveis em string.
            $table->string('classificacao', 40);
            $table->timestamps();

            $table->unique(['empresa_id', 'plano_conta_id'], 'dre_plano_class_emp_plano_uq');
            $table->index(['empresa_id', 'classificacao'], 'dre_plano_class_emp_class_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_plano_classificacoes');
    }
};
