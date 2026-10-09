<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('empresas', 'param_pdv_mesa_inatividade_min')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('param_pdv_mesa_inatividade_min')->nullable()->default(1);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('empresas', 'param_pdv_mesa_inatividade_min')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('param_pdv_mesa_inatividade_min');
        });
    }
};
