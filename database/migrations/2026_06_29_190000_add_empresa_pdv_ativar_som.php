<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_pdv_ativar_som')) {
                $table->boolean('param_pdv_ativar_som')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_pdv_ativar_som')) {
                $table->dropColumn('param_pdv_ativar_som');
            }
        });
    }
};
