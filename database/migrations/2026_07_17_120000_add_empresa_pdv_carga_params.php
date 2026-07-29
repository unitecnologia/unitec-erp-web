<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetros da carga automática do mini-PDV offline (lidos pelo PDV via carga).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_pdv_carga_auto')) {
                $table->boolean('param_pdv_carga_auto')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_carga_intervalo_min')) {
                $table->integer('param_pdv_carga_intervalo_min')->default(15);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach (['param_pdv_carga_auto', 'param_pdv_carga_intervalo_min'] as $col) {
                if (Schema::hasColumn('empresas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
