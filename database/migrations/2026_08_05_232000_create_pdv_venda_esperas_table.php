<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_esperas')) {
            Schema::create('pdv_venda_esperas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pdv_caixa_sessao_id')->constrained('pdv_caixa_sessoes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
                $table->unsignedInteger('sequencia');
                $table->string('cliente_nome')->nullable();
                $table->string('vendedor_nome')->nullable();
                $table->unsignedInteger('qtd_itens')->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->longText('snapshot');
                $table->timestamps();

                // Nomes curtos: MariaDB limita identificadores a 64 caracteres,
                // inclusive quando DB_PREFIX adiciona "unitec_" à tabela.
                $table->index(['pdv_caixa_sessao_id', 'user_id', 'created_at'], 'pdv_espera_sessao_user_idx');
                $table->unique(['pdv_caixa_sessao_id', 'sequencia'], 'pdv_espera_sessao_seq_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_venda_esperas');
    }
};
