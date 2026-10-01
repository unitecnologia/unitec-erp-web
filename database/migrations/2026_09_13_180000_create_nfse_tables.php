<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfse_dps_sequencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('serie_dps', 10);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'serie_dps'], 'nfse_dps_sequencias_empresa_serie_unique');
        });

        Schema::create('nfses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('tomador_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('tomador_nome');
            $table->string('tomador_cpf_cnpj')->nullable();
            $table->string('tomador_telefone')->nullable();
            $table->string('tomador_endereco')->nullable();
            $table->string('tomador_numero')->nullable();
            $table->string('tomador_bairro')->nullable();
            $table->string('tomador_cep', 20)->nullable();
            $table->string('tomador_cidade')->nullable();
            $table->string('tomador_uf', 2)->nullable();
            $table->date('competencia');
            $table->date('data_emissao');
            $table->string('municipio_incidencia')->nullable();
            $table->string('serie_dps', 10);
            $table->unsignedBigInteger('numero_dps');
            $table->string('numero_nfse', 30)->nullable();
            $table->string('chave', 60)->nullable();
            $table->string('protocolo', 40)->nullable();
            $table->decimal('valor_servicos', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('iss', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('aberta');
            $table->timestamps();

            $table->unique(['empresa_id', 'serie_dps', 'numero_dps'], 'nfses_empresa_serie_numero_unique');
            $table->index(['empresa_id', 'status', 'data_emissao'], 'nfses_empresa_status_emissao_index');
        });

        Schema::create('nfse_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfse_id')->constrained('nfses')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->string('codigo');
            $table->string('descricao');
            $table->string('unidade')->nullable();
            $table->decimal('quantidade', 15, 3);
            $table->decimal('valor', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();

            $table->index(['nfse_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_itens');
        Schema::dropIfExists('nfses');
        Schema::dropIfExists('nfse_dps_sequencias');
    }
};
