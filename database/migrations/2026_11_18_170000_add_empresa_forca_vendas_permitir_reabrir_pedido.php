<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_forca_vendas_permitir_reabrir_pedido')) {
                // Padrão false: app antigo e empresas sem opt-in continuam só com push idempotente (duplicado).
                $table->boolean('param_forca_vendas_permitir_reabrir_pedido')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_forca_vendas_permitir_reabrir_pedido')) {
                $table->dropColumn('param_forca_vendas_permitir_reabrir_pedido');
            }
        });
    }
};
