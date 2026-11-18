<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_uniformes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->string('descricao', 160);
            $table->string('tipo', 40)->nullable(); // camisa|calça|jaleco|calçado|outro
            $table->string('tamanho', 20)->nullable(); // P|M|G|GG|numeração|outro
            $table->string('cor', 40)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['ativo', 'descricao']);
            $table->index('tipo');
            $table->index('tamanho');
        });

        Schema::create('rh_uniforme_entregas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20);
            $table->foreignId('funcionario_id')->constrained('rh_funcionarios')->restrictOnDelete();
            $table->foreignId('uniforme_id')->constrained('rh_uniformes')->restrictOnDelete();
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->date('entregue_em');
            $table->string('situacao', 20)->default('entregue'); // entregue|devolvido|trocado|substituido|cancelado
            $table->text('observacao')->nullable();
            $table->string('motivo_movimentacao', 255)->nullable();
            $table->date('devolvido_em')->nullable();
            $table->foreignId('substitui_id')->nullable()->constrained('rh_uniforme_entregas')->nullOnDelete();
            $table->foreignId('substituido_por_id')->nullable()->constrained('rh_uniforme_entregas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['funcionario_id', 'situacao']);
            $table->index(['uniforme_id', 'situacao']);
            $table->index(['ativo', 'situacao']);
            $table->index('entregue_em');
        });

        Schema::create('rh_uniforme_entrega_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entrega_id')->constrained('rh_uniforme_entregas')->cascadeOnDelete();
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
        Schema::dropIfExists('rh_uniforme_entrega_logs');
        Schema::dropIfExists('rh_uniforme_entregas');
        Schema::dropIfExists('rh_uniformes');
    }
};
