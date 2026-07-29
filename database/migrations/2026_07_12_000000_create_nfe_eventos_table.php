<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo', 120);
            $table->text('descricao')->nullable();
            $table->string('destinatario', 255)->nullable();
            $table->string('referencia_tipo', 80)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['nfe_id', 'created_at']);
            $table->index(['nfe_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_eventos');
    }
};
