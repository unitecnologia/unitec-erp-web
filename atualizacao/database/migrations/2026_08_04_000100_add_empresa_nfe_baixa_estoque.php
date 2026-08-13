<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_fiscal_nfe_baixa_estoque')) {
                $table->boolean('param_fiscal_nfe_baixa_estoque')->default(true);
            }
        });

        Schema::table('nfes', function (Blueprint $table) {
            if (! Schema::hasColumn('nfes', 'estoque_baixado')) {
                $table->boolean('estoque_baixado')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_fiscal_nfe_baixa_estoque')) {
                $table->dropColumn('param_fiscal_nfe_baixa_estoque');
            }
        });

        Schema::table('nfes', function (Blueprint $table) {
            if (Schema::hasColumn('nfes', 'estoque_baixado')) {
                $table->dropColumn('estoque_baixado');
            }
        });
    }
};
