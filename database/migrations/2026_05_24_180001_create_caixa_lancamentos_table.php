<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->date('emissao');
            $table->string('documento', 40)->nullable();
            $table->string('historico', 500);
            $table->string('plano_contas', 120)->nullable();
            $table->foreignId('caixa_conta_id')->constrained('caixa_contas')->cascadeOnDelete();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->timestamps();

            $table->index('emissao');
            $table->index('caixa_conta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_lancamentos');
    }
};
