<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_forca_vendas_public_url')) {
                $table->text('param_forca_vendas_public_url')->nullable()->after('param_gestor_public_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_forca_vendas_public_url')) {
                $table->dropColumn('param_forca_vendas_public_url');
            }
        });
    }
};
