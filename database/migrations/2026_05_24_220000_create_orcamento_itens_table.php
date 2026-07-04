<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_grade_id')->nullable()->constrained('product_grades')->nullOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('descricao')->nullable();
            $table->timestamps();

            $table->index(['orcamento_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
    }
};
