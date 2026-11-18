<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_treinamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 160);
            $table->text('descricao')->nullable();
            $table->string('tipo', 60)->nullable();
            $table->decimal('carga_horaria', 6, 2)->nullable();
            $table->string('instrutor', 120)->nullable();
            $table->unsignedSmallInteger('periodicidade_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['ativo', 'nome']);
            $table->index('tipo');
        });

        Schema::create('rh_treinamento_realizacoes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->foreignId('treinamento_id')->constrained('rh_treinamentos')->restrictOnDelete();
            $table->date('realizado_em');
            $table->date('vencimento_em')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['treinamento_id', 'realizado_em'], 'rh_trein_realiz_trein_data_idx');
            $table->index(['vencimento_em', 'ativo'], 'rh_trein_realiz_venc_ativo_idx');
            $table->index(['ativo', 'realizado_em'], 'rh_trein_realiz_ativo_data_idx');
        });

        Schema::create('rh_treinamento_participantes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->foreignId('realizacao_id')->constrained('rh_treinamento_realizacoes')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
            $table->date('vencimento_em')->nullable();
            $table->string('situacao', 20)->default('ativo'); // ativo|cancelado
            $table->string('observacao', 255)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->unique(['realizacao_id', 'funcionario_id'], 'rh_trein_part_realiz_func_uq');
            $table->index(['funcionario_id', 'situacao'], 'rh_trein_part_func_sit_idx');
            $table->index(['vencimento_em', 'situacao'], 'rh_trein_part_venc_sit_idx');
            $table->index(['ativo', 'situacao'], 'rh_trein_part_ativo_sit_idx');
        });

        Schema::create('rh_treinamento_realizacao_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('realizacao_id')->constrained('rh_treinamento_realizacoes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['realizacao_id', 'created_at'], 'rh_trein_realiz_log_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_treinamento_realizacao_logs');
        Schema::dropIfExists('rh_treinamento_participantes');
        Schema::dropIfExists('rh_treinamento_realizacoes');
        Schema::dropIfExists('rh_treinamentos');
    }
};
