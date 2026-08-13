<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_imp_ibpt_token')) {
                // TEXT: unitec_empresas já está no limite de row size InnoDB (VARCHAR estoura).
                $table->text('param_imp_ibpt_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_imp_ibpt_token')) {
                $table->dropColumn('param_imp_ibpt_token');
            }
        });
    }
};
