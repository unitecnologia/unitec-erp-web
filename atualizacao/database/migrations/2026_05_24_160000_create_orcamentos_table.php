<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->date('data');
            $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('people')->nullOnDelete();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('aberto');
            $table->timestamps();

            $table->index('data');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};
