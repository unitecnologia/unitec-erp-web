<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_fornecedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('codigo_fornecedor', 60);
            $table->timestamps();

            $table->unique(['person_id', 'codigo_fornecedor'], 'produto_fornecedores_person_codigo_unique');
            $table->index(['product_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_fornecedores');
    }
};
