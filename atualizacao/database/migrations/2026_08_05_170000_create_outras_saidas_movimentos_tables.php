<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outras_saidas_movimentos')) {
            Schema::create('outras_saidas_movimentos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
                $table->string('numero', 30)->index();
                $table->string('situacao', 20)->default('aberta')->index();
                $table->string('tipo_movimento', 30)->default('saida');
                $table->date('data')->index();
                $table->time('hora')->nullable();
                $table->foreignId('estoque_id')->nullable()->constrained('estoques')->nullOnDelete();
                $table->foreignId('fornecedor_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('fornecedor_nome', 150)->nullable();
                $table->string('observacoes', 250)->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['empresa_id', 'numero']);
            });
        }

        if (! Schema::hasTable('outras_saida_movimento_itens')) {
            Schema::create('outras_saida_movimento_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('outras_saida_movimento_id');
            $table->foreign('outras_saida_movimento_id', 'osm_item_mov_fk')
                ->references('id')
                ->on('outras_saidas_movimentos')
                ->cascadeOnDelete();
            $table->unsignedInteger('item');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('produto_codigo', 40)->nullable();
            $table->string('produto_descricao', 200)->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->decimal('preco', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outras_saida_movimento_itens');
        Schema::dropIfExists('outras_saidas_movimentos');
    }
};
