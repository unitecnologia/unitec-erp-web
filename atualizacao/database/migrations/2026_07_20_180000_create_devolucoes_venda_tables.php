<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucoes_venda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedInteger('codigo_legado')->nullable()->index();
            $table->string('numero', 30)->nullable()->index();
            $table->string('situacao', 20)->default('aberta')->index();
            $table->string('tipo_devolucao', 20)->nullable();
            $table->date('data')->nullable()->index();
            $table->time('hora')->nullable();

            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->string('venda_numero', 30)->nullable()->index();
            $table->foreignId('cliente_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('cliente_nome', 150)->nullable();
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('observacoes', 250)->nullable();
            $table->decimal('total', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['empresa_id', 'codigo_legado']);
        });

        Schema::create('devolucao_venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('devolucao_venda_id')->constrained('devolucoes_venda')->cascadeOnDelete();
            $table->unsignedInteger('codigo_legado')->nullable()->index();
            $table->unsignedInteger('item')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('venda_item_id')->nullable()->constrained('venda_itens')->nullOnDelete();
            $table->string('produto_codigo', 40)->nullable();
            $table->string('produto_descricao', 200)->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->decimal('qtd_vendida', 15, 3)->default(0);
            $table->decimal('preco', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('grade_legado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucao_venda_itens');
        Schema::dropIfExists('devolucoes_venda');
    }
};
