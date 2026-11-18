<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_exames', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
            // Tipos alinhados à NR-7 / preparação eSocial S-2220 (sem transmissão nesta etapa).
            $table->string('tipo', 40);
            $table->date('realizado_em');
            $table->date('proximo_em')->nullable(); // só quando informado (PCMSO); sem presumir
            $table->string('medico_nome', 120)->nullable();
            $table->string('medico_crm', 40)->nullable();
            // Conclusão do ASO emitido (não é diagnóstico clínico).
            $table->string('conclusao', 40)->default('nao_informada');
            $table->string('observacao', 500)->nullable(); // administrativa
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['funcionario_id', 'realizado_em']);
            $table->index(['proximo_em', 'ativo']);
            $table->index(['tipo', 'ativo']);
            $table->index(['ativo', 'realizado_em']);
        });

        Schema::create('rh_exame_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exame_id')->constrained('rh_exames')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['exame_id', 'created_at']);
            $table->index(['acao', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_exame_logs');
        Schema::dropIfExists('rh_exames');
    }
};
