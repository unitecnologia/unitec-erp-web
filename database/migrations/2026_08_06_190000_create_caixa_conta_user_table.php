<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caixas liberados por usuário e empresa (empresa padrão de operação).
 * caixa_contas continua global; o vínculo restringe o uso por loja/usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caixa_conta_user')) {
            return;
        }

        Schema::create('caixa_conta_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caixa_conta_id')->constrained('caixa_contas')->cascadeOnDelete();
            $table->boolean('is_padrao')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'empresa_id', 'caixa_conta_id'], 'caixa_conta_user_uq');
            $table->index(['user_id', 'empresa_id'], 'caixa_conta_user_user_empresa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_conta_user');
    }
};
