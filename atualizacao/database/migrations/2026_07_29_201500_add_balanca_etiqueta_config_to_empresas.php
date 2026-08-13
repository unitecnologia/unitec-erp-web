<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_balanca_etiqueta_modelo')) {
                $table->unsignedTinyInteger('param_balanca_etiqueta_modelo')->nullable()->after('param_balanca_diretorio');
            }

            if (! Schema::hasColumn('empresas', 'param_balanca_prefixo_barra')) {
                $table->string('param_balanca_prefixo_barra', 2)->nullable()->after('param_balanca_etiqueta_modelo');
            }

            if (! Schema::hasColumn('empresas', 'param_balanca_digitos')) {
                $table->unsignedTinyInteger('param_balanca_digitos')->nullable()->after('param_balanca_prefixo_barra');
            }
        });

        // Migra modelo já usado no PDV (param_pdv_modelo_balanca) quando existir.
        if (Schema::hasColumn('empresas', 'param_pdv_modelo_balanca')
            && Schema::hasColumn('empresas', 'param_balanca_etiqueta_modelo')) {
            DB::table('empresas')
                ->whereNull('param_balanca_etiqueta_modelo')
                ->update([
                    'param_balanca_etiqueta_modelo' => DB::raw('COALESCE(param_pdv_modelo_balanca, 4)'),
                    'param_balanca_prefixo_barra' => DB::raw("COALESCE(param_balanca_prefixo_barra, '2')"),
                    'param_balanca_digitos' => DB::raw('COALESCE(param_balanca_digitos, CASE COALESCE(param_pdv_modelo_balanca, 4)
                        WHEN 1 THEN 4
                        WHEN 2 THEN 5
                        WHEN 3 THEN 5
                        ELSE 6
                    END)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ([
                'param_balanca_digitos',
                'param_balanca_prefixo_barra',
                'param_balanca_etiqueta_modelo',
            ] as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
