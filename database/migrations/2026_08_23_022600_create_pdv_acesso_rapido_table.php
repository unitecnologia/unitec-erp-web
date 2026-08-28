<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_acesso_rapido', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terminal_id')->unique()->constrained('terminais')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->unsignedSmallInteger('slots_count')->default(30);
            $table->json('itens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_acesso_rapido');
    }
};
