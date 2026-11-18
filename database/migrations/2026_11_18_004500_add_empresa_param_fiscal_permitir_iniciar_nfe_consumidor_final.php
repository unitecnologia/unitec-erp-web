<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetro Fiscal: permitir iniciar NF-e no Monitor com Consumidor Final.
 * Default false — preserva o bloqueio atual de cadastro incompleto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_fiscal_permitir_iniciar_nfe_consumidor_final')) {
                $table->boolean('param_fiscal_permitir_iniciar_nfe_consumidor_final')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_fiscal_permitir_iniciar_nfe_consumidor_final')) {
                $table->dropColumn('param_fiscal_permitir_iniciar_nfe_consumidor_final');
            }
        });
    }
};
