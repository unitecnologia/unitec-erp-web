<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedInteger('codigo_legado')->nullable()->index();
            $table->string('numero', 30)->nullable()->index();
            $table->string('situacao', 20)->default('aberta')->index();
            $table->date('data_inicio')->nullable()->index();
            $table->time('hora_inicio')->nullable();
            $table->dateTime('previsao_entrega')->nullable();
            $table->date('data_termino')->nullable();
            $table->time('hora_termino')->nullable();
            $table->date('data_entrega')->nullable();
            $table->time('hora_entrega')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('proxima_revisao')->nullable();
            $table->boolean('avisar_revisao')->default(false);

            $table->foreignId('cliente_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('atendente_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('documento', 20)->nullable();
            $table->string('nome', 150)->nullable();
            $table->string('fone1', 20)->nullable();
            $table->string('fone2', 20)->nullable();
            $table->string('endereco', 150)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->string('uf', 2)->nullable();

            $table->string('numero_serie', 60)->nullable();
            $table->string('descricao', 150)->nullable();
            $table->string('descricao2', 150)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('ano', 10)->nullable();
            $table->string('placa', 15)->nullable()->index();
            $table->unsignedInteger('km')->nullable();

            $table->string('modelo_veiculo', 80)->nullable();
            $table->string('categoria_veiculo', 40)->nullable();
            $table->string('marca_veiculo', 80)->nullable();
            $table->string('ano_veiculo', 10)->nullable();
            $table->string('cor_veiculo', 40)->nullable();
            $table->string('placa_veiculo', 15)->nullable();
            $table->string('combustivel_veiculo', 30)->nullable();
            $table->string('chassi_veiculo', 40)->nullable();

            $table->string('tipo_servico', 80)->nullable();
            $table->string('nome_time', 80)->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->unsignedInteger('tipo_tecido_legado')->nullable();

            $table->text('problema')->nullable();
            $table->text('observacoes')->nullable();
            $table->text('laudo')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('subtotal_pecas', 15, 2)->default(0);
            $table->decimal('subtotal_servicos', 15, 2)->default(0);
            $table->decimal('vl_desc_pecas', 15, 2)->default(0);
            $table->decimal('vl_desc_servicos', 15, 2)->default(0);
            $table->decimal('desc_perc_pecas', 8, 4)->default(0);
            $table->decimal('desc_perc_servicos', 8, 4)->default(0);
            $table->decimal('total_servicos', 15, 2)->default(0);
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_geral', 15, 2)->default(0);

            $table->string('envio_whats_status', 30)->nullable();
            $table->string('path_pdf_whats', 250)->nullable();
            $table->string('numero_whatsapp', 30)->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'codigo_legado']);
        });

        Schema::create('ordem_servico_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->unsignedInteger('codigo_legado')->nullable()->index();
            $table->foreignId('funcionario_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('tipo', 1)->nullable()->index();
            $table->string('situacao', 20)->nullable();
            $table->string('discriminacao', 250)->nullable();
            $table->date('data_inicio')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->date('data_termino')->nullable();
            $table->time('hora_termino')->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->decimal('preco', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('cor', 40)->nullable();
            $table->string('tamanho', 40)->nullable();
            $table->string('detalhe', 80)->nullable();
            $table->string('nome', 120)->nullable();
            $table->string('numero', 40)->nullable();
            $table->unsignedInteger('grade_legado')->nullable();
            $table->timestamps();
        });

        Schema::create('ordem_servico_imagens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->unsignedInteger('codigo_legado')->nullable();
            $table->unsignedInteger('item')->nullable();
            $table->string('caminho', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_imagens');
        Schema::dropIfExists('ordem_servico_itens');
        Schema::dropIfExists('ordens_servico');
    }
};
