<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('descricao', 120);
            $table->decimal('quantidade', 15, 4)->default(1);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_itens');
    }
};
