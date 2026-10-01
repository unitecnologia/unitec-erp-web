<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carga_entregas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('carga_id')->constrained('cargas')->cascadeOnDelete();
            $table->unsignedBigInteger('pedido_id');
            $table->foreignId('entregador_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('app_local_uuid')->unique();
            $table->string('status', 30)->default('entregue');
            $table->text('observacao')->nullable();
            $table->string('foto_path')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->foreign('pedido_id')->references('id')->on('vendas')->restrictOnDelete();
            $table->unique(['carga_id', 'pedido_id']);
            $table->index(['empresa_id', 'entregador_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_entregas');
    }
};
