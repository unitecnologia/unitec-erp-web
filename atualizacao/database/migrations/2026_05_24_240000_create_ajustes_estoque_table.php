<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_estoque', function (Blueprint $table): void {
            $table->id();
            $table->date('data');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qtd_ajust', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_estoque');
    }
};
