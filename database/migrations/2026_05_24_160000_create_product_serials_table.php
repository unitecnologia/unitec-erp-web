<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('numero_serie');
            $table->string('doc_saida')->nullable();
            $table->string('situacao')->default('DISPONIVEL');
            $table->date('data_baixa')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'numero_serie']);
            $table->index('numero_serie');
            $table->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
