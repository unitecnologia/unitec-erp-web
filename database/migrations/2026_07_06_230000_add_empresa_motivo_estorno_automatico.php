<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_fiscal_motivo_estorno_automatico')) {
                $table->boolean('param_fiscal_motivo_estorno_automatico')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_fiscal_motivo_estorno_automatico')) {
                $table->dropColumn('param_fiscal_motivo_estorno_automatico');
            }
        });
    }
};
