<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_cargos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index('ativo');
        });

        Schema::create('rh_departamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index('ativo');
        });

        Schema::create('rh_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 30)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->foreignId('cargo_id')->nullable()->constrained('rh_cargos')->nullOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('rh_departamentos')->nullOnDelete();
            $table->decimal('salario', 15, 2)->nullable();
            $table->date('data_admissao')->nullable();
            $table->date('data_demissao')->nullable();
            $table->string('foto_path', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['ativo', 'nome']);
            $table->index('cpf');
            $table->index('data_admissao');
            $table->index('data_demissao');
        });

        Schema::create('rh_anexos', function (Blueprint $table): void {
            $table->id();
            $table->morphs('anexavel');
            $table->string('categoria', 40);
            $table->string('titulo', 160);
            $table->string('caminho', 255);
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->date('emitido_em')->nullable();
            $table->date('valido_ate')->nullable();
            $table->date('entregue_em')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['categoria', 'valido_ate']);
            $table->index(['valido_ate', 'ativo']);
        });

        Schema::create('rh_escalas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index('ativo');
        });

        Schema::create('rh_escala_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('escala_id')->constrained('rh_escalas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo … 6=sábado
            $table->string('tipo', 20)->default('trabalho'); // trabalho|folga|plantao
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->timestamps();

            $table->unique(['escala_id', 'funcionario_id', 'dia_semana'], 'rh_escala_itens_unique');
            $table->index(['funcionario_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_escala_itens');
        Schema::dropIfExists('rh_escalas');
        Schema::dropIfExists('rh_anexos');
        Schema::dropIfExists('rh_funcionarios');
        Schema::dropIfExists('rh_departamentos');
        Schema::dropIfExists('rh_cargos');
    }
};
