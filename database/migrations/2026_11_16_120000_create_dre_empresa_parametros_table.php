<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE V1 — parâmetros de tributos por empresa (backup/restauração via tabela).
 * Padrões: exibir=não, estimativa=não, alíquota=null (sem presumir %).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dre_empresa_parametros')) {
            return;
        }

        Schema::create('dre_empresa_parametros', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->unique();
            $table->boolean('exibir_tributos')->default(false);
            $table->boolean('calcular_estimativa_simples')->default(false);
            // null = não informado (não presume alíquota).
            $table->decimal('aliquota_simples_pct', 7, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_empresa_parametros');
    }
};
