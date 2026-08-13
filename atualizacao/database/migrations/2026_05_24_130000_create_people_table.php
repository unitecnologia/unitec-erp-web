<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nome_razao');
            $table->string('apelido_fantasia')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->string('rg_ie')->nullable();
            $table->string('endereco')->nullable();
            $table->string('tipo')->default('cliente');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
