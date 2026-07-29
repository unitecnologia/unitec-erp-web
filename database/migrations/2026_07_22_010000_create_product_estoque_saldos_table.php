<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_estoque_saldos')) {
            return;
        }

        Schema::create('product_estoque_saldos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('estoque_id')->constrained('estoques')->cascadeOnDelete();
            $table->decimal('quantidade', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'estoque_id']);
            $table->index(['estoque_id', 'quantidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_estoque_saldos');
    }
};
