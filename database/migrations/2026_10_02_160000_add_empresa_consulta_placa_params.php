<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_consulta_placa_habilitar')) {
                $table->boolean('param_consulta_placa_habilitar')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_consulta_placa_url')) {
                $table->text('param_consulta_placa_url')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_consulta_placa_token')) {
                $table->text('param_consulta_placa_token')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_consulta_placa_timeout')) {
                $table->unsignedSmallInteger('param_consulta_placa_timeout')->default(10);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            foreach ([
                'param_consulta_placa_habilitar',
                'param_consulta_placa_url',
                'param_consulta_placa_token',
                'param_consulta_placa_timeout',
            ] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
