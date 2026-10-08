<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo rastreável NF-e ↔ orçamento importado (uma NF-e pode importar vários orçamentos).
 * Usado para bloquear faturamento duplicado do orçamento (PDV F3, NF-e, Tela de Venda).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_orcamentos')) {
            return;
        }

        Schema::create('nfe_orcamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->string('status_anterior', 20)->nullable();
            $table->timestamps();

            $table->unique(['nfe_id', 'orcamento_id']);
            $table->index('orcamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_orcamentos');
    }
};
