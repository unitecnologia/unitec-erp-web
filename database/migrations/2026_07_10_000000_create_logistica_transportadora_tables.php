<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transportadoras')) {
            Schema::create('transportadoras', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->string('tipo_pessoa', 1)->default('J');
                $table->string('cnpj_cpf', 20)->nullable();
                $table->string('rg_ie', 30)->nullable();
                $table->string('cep', 10)->nullable();
                $table->string('proprietario', 120);
                $table->string('apelido', 120)->nullable();
                $table->string('endereco', 120)->nullable();
                $table->string('numero', 20)->nullable();
                $table->string('bairro', 80)->nullable();
                $table->string('cidade', 80)->nullable();
                $table->string('codigo_municipio', 10)->nullable();
                $table->string('uf', 2)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
            });
        }

        if (! Schema::hasTable('transportadora_motoristas')) {
            Schema::create('transportadora_motoristas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('transportadora_id')->constrained('transportadoras')->cascadeOnDelete();
                $table->string('nome', 120);
                $table->string('cpf', 14)->nullable();
                $table->unsignedSmallInteger('ordem')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('veiculos')) {
            Schema::create('veiculos', function (Blueprint $table): void {
                $table->id();
                $table->string('placa', 10);
                $table->string('descricao', 120)->nullable();
                $table->string('renavam', 20)->nullable();
                $table->string('rntc', 20)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('placa');
            });
        }

        if (! Schema::hasTable('tomadores_servico')) {
            Schema::create('tomadores_servico', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nome', 120);
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
            });
        }

        if (! Schema::hasTable('logistica_destinatarios')) {
            Schema::create('logistica_destinatarios', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nome', 120);
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
            });
        }

        if (! Schema::hasTable('logistica_remetentes')) {
            Schema::create('logistica_remetentes', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nome', 120);
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique('codigo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistica_remetentes');
        Schema::dropIfExists('logistica_destinatarios');
        Schema::dropIfExists('tomadores_servico');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('transportadora_motoristas');
        Schema::dropIfExists('transportadoras');
    }
};
