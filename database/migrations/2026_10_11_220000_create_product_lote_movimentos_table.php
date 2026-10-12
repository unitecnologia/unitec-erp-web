<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_lote_movimentos')) {
            return;
        }

        Schema::create('product_lote_movimentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('product_lote_id')->nullable()->index();
            $table->string('lote', 60);
            $table->date('data_validade');
            $table->decimal('quantidade', 12, 3);
            $table->string('sentido', 10); // saida | entrada
            $table->string('origem_tipo', 40);
            $table->unsignedBigInteger('origem_id');
            $table->unsignedBigInteger('referencia_movimento_id')->nullable()->index();
            $table->timestamps();

            $table->index(['origem_tipo', 'origem_id']);
            $table->index(['product_id', 'sentido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lote_movimentos');
    }
};
