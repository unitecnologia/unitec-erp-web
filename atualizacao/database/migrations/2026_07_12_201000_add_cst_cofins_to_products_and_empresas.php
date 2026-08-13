<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_imp_cst_cofins')) {
                $table->string('param_imp_cst_cofins', 40)->nullable()->default('01');
            }
        });

        if (Schema::hasColumn('empresas', 'param_imp_cofins_cst')
            && Schema::hasColumn('empresas', 'param_imp_cst_cofins')) {
            DB::table('empresas')
                ->where(function ($query): void {
                    $query->whereNull('param_imp_cst_cofins')
                        ->orWhere('param_imp_cst_cofins', '');
                })
                ->update([
                    'param_imp_cst_cofins' => DB::raw("COALESCE(NULLIF(param_imp_cofins_cst, ''), '01')"),
                ]);
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'cst_cofins')) {
                $table->string('cst_cofins', 3)->default('01')->after('cst_saida');
            }
        });

        if (Schema::hasColumn('products', 'cst_cofins')
            && Schema::hasColumn('products', 'cst_saida')) {
            DB::table('products')
                ->where(function ($query): void {
                    $query->whereNull('cst_cofins')->orWhere('cst_cofins', '');
                })
                ->update([
                    'cst_cofins' => DB::raw("COALESCE(NULLIF(cst_saida, ''), '01')"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cst_cofins')) {
                $table->dropColumn('cst_cofins');
            }
        });

        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_imp_cst_cofins')) {
                $table->dropColumn('param_imp_cst_cofins');
            }
        });
    }
};
