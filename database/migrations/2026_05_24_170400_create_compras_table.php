<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->date('data_emissao');
            $table->date('data_entrada')->nullable();
            $table->string('numero_nota', 20)->nullable();
            $table->foreignId('fornecedor_id')->constrained('people')->cascadeOnDelete();
            $table->string('chave_nfe', 44)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('aberta');
            $table->timestamps();

            $table->index('data_emissao');
            $table->index('data_entrada');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
