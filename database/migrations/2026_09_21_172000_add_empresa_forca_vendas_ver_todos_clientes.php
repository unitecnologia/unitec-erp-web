<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_forca_vendas_ver_todos_clientes')) {
                $table->boolean('param_forca_vendas_ver_todos_clientes')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_forca_vendas_ver_todos_clientes')) {
                $table->dropColumn('param_forca_vendas_ver_todos_clientes');
            }
        });
    }
};
