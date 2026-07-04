<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_contas');
    }
};
