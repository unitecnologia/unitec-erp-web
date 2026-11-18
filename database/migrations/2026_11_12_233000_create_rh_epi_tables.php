<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_epis', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('descricao', 160);
            $table->string('ca_numero', 40)->nullable();
            $table->date('ca_valido_ate')->nullable();
            $table->string('fabricante', 120)->nullable();
            $table->date('validade_equipamento')->nullable();
            $table->unsignedSmallInteger('intervalo_troca_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['ativo', 'descricao']);
            $table->index('ca_numero');
            $table->index('ca_valido_ate');
        });

        Schema::create('rh_epi_entregas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
            $table->foreignId('epi_id')->constrained('rh_epis')->restrictOnDelete();
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->date('entregue_em');
            $table->date('previsao_troca')->nullable();
            $table->string('situacao', 20)->default('entregue'); // entregue|devolvido|substituido|cancelado
            $table->text('observacao')->nullable();
            $table->string('motivo_substituicao', 255)->nullable();
            $table->date('devolvido_em')->nullable();
            $table->foreignId('substitui_id')->nullable()->constrained('rh_epi_entregas')->nullOnDelete();
            $table->foreignId('substituido_por_id')->nullable()->constrained('rh_epi_entregas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['funcionario_id', 'situacao']);
            $table->index(['epi_id', 'situacao']);
            $table->index(['previsao_troca', 'situacao']);
            $table->index(['ativo', 'situacao']);
        });

        Schema::create('rh_epi_entrega_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entrega_id')->constrained('rh_epi_entregas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 40);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entrega_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_epi_entrega_logs');
        Schema::dropIfExists('rh_epi_entregas');
        Schema::dropIfExists('rh_epis');
    }
};
