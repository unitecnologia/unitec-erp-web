<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_geral_usar_pdv_erp')) {
                $table->boolean('param_geral_usar_pdv_erp')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_geral_usar_pdv_erp')) {
                $table->dropColumn('param_geral_usar_pdv_erp');
            }
        });
    }
};
