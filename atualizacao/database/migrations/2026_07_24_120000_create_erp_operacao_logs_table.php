<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_operacao_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ocorrido_em')->useCurrent();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nome', 120)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('operacao', 80);
            $table->string('origem', 80)->nullable();
            $table->string('documento_tipo', 40)->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->string('documento_numero', 60)->nullable();
            $table->string('resultado', 20)->default('ok');
            $table->text('resumo')->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamps();

            $table->index(['ocorrido_em']);
            $table->index(['operacao', 'ocorrido_em']);
            $table->index(['documento_tipo', 'documento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_operacao_logs');
    }
};
