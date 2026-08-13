<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabelas_prazo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento')->cascadeOnDelete();
            $table->string('dias', 191);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabelas_prazo');
    }
};
