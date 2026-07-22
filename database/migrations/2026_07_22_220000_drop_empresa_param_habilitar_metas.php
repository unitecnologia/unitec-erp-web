<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metas passam a depender só do valor preenchido (> 0), sem flag de empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_habilitar_metas')) {
                $table->dropColumn('param_habilitar_metas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_habilitar_metas')) {
                $table->boolean('param_habilitar_metas')->default(false);
            }
        });
    }
};
