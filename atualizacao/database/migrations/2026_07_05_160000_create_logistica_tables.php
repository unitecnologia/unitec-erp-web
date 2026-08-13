<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistica_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20);
            $table->date('data');
            $table->foreignId('entregador_id')->nullable()->constrained('entregadores')->nullOnDelete();
            $table->string('motorista_nome')->nullable();
            $table->string('status', 30)->default('montando');
            $table->text('observacoes')->nullable();
            $table->timestamp('saiu_em')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'data']);
        });

        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20);
            $table->foreignId('venda_id')->unique()->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('cliente_nome')->nullable();
            $table->string('cliente_telefone')->nullable();
            $table->string('endereco_cep', 12)->nullable();
            $table->string('endereco_logradouro')->nullable();
            $table->string('endereco_numero', 30)->nullable();
            $table->string('endereco_complemento')->nullable();
            $table->string('endereco_bairro')->nullable();
            $table->string('endereco_cidade')->nullable();
            $table->string('endereco_uf', 2)->nullable();
            $table->string('endereco_completo')->nullable();
            $table->string('status', 40)->default('aguardando_separacao');
            $table->string('origem', 20)->default('erp');
            $table->text('observacoes')->nullable();
            $table->foreignId('entregador_id')->nullable()->constrained('entregadores')->nullOnDelete();
            $table->foreignId('logistica_carga_id')->nullable()->constrained('logistica_cargas')->nullOnDelete();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('entrega_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();
            $table->foreignId('venda_item_id')->nullable()->constrained('venda_itens')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('codigo', 60)->nullable();
            $table->string('descricao');
            $table->string('localizacao')->nullable();
            $table->decimal('quantidade_pedida', 12, 3);
            $table->decimal('quantidade_separada', 12, 3)->default(0);
            $table->decimal('quantidade_conferida', 12, 3)->default(0);
            $table->boolean('separado')->default(false);
            $table->boolean('conferido')->default(false);
            $table->timestamps();

            $table->index(['entrega_id', 'separado']);
        });

        Schema::create('entrega_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('de_status', 40)->nullable();
            $table->string('para_status', 40);
            $table->text('observacao')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entrega_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_eventos');
        Schema::dropIfExists('entrega_itens');
        Schema::dropIfExists('entregas');
        Schema::dropIfExists('logistica_cargas');
    }
};
