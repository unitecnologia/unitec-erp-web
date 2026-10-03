<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('os_veiculos')) {
            return;
        }

        Schema::create('os_veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('placa', 10);
            $table->string('placa_alternativa', 10)->nullable();
            $table->string('descricao', 160)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('submodelo', 80)->nullable();
            $table->string('versao', 80)->nullable();
            $table->string('ano_fabricacao', 4)->nullable();
            $table->string('ano_modelo', 4)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('renavam', 20)->nullable();
            $table->string('chassi', 30)->nullable();
            $table->string('cor', 40)->nullable();
            $table->string('combustivel', 40)->nullable();
            $table->string('tipo', 60)->nullable();
            $table->string('especie', 60)->nullable();
            $table->string('carroceria', 60)->nullable();
            $table->string('origem', 60)->nullable();
            $table->string('nacionalidade', 60)->nullable();
            $table->string('segmento', 60)->nullable();
            $table->string('subsegmento', 60)->nullable();
            $table->dateTime('consultado_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_veiculos');
    }
};
