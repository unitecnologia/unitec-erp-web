<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_acrescimo_maximo')) {
                $table->decimal('param_acrescimo_maximo', 12, 2)->default('0.00');
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_modelo_balanca')) {
                $table->unsignedTinyInteger('param_pdv_modelo_balanca')->default(4);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_exibir_f3_vendedor')) {
                $table->boolean('param_pdv_exibir_f3_vendedor')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_pdv_exibir_f4_busca_avancada')) {
                $table->boolean('param_pdv_exibir_f4_busca_avancada')->default(false);
            }
        });

        Schema::table('pdv_caixa_movimentos', function (Blueprint $table) {
            if (! Schema::hasColumn('pdv_caixa_movimentos', 'plano_conta_codigo')) {
                $table->unsignedInteger('plano_conta_codigo')->nullable()->after('forma_pagamento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_caixa_movimentos', function (Blueprint $table) {
            if (Schema::hasColumn('pdv_caixa_movimentos', 'plano_conta_codigo')) {
                $table->dropColumn('plano_conta_codigo');
            }
        });

        Schema::table('empresas', function (Blueprint $table) {
            $columns = [
                'param_acrescimo_maximo',
                'param_pdv_modelo_balanca',
                'param_pdv_exibir_f3_vendedor',
                'param_pdv_exibir_f4_busca_avancada',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
