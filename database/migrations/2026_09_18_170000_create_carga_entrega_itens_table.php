<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carga_entrega_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('carga_entrega_id')->constrained('carga_entregas')->cascadeOnDelete();
            $table->unsignedBigInteger('produto_id')->nullable();
            $table->string('codigo', 60)->nullable();
            $table->string('descricao', 255);
            $table->decimal('quantidade_original', 15, 3)->default(0);
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->string('unidade', 20)->nullable();
            $table->timestamps();

            $table->index('carga_entrega_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_entrega_itens');
    }
};
