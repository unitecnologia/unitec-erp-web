<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto do letreiro (marquee) exibido correndo no topo do PDV, no lugar do nome
 * da empresa. Configurado nos parâmetros da empresa e distribuído ao PDV offline
 * pela carga (vai junto dos demais param_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_pdv_marquee_texto')) {
                $table->string('param_pdv_marquee_texto')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_pdv_marquee_texto')) {
                $table->dropColumn('param_pdv_marquee_texto');
            }
        });
    }
};
