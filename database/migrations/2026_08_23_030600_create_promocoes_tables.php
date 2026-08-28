<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('descricao', 120);
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'ativa', 'data_inicio', 'data_fim']);
        });

        Schema::create('promocao_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promocao_id')->constrained('promocoes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('preco_promocao', 12, 2);
            $table->boolean('mostrar_pdv')->default(false);
            $table->timestamps();

            $table->unique(['promocao_id', 'product_id']);
            $table->index(['product_id', 'mostrar_pdv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocao_itens');
        Schema::dropIfExists('promocoes');
    }
};
