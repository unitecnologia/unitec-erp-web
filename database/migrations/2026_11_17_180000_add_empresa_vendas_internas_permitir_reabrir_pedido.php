<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_vendas_internas_permitir_reabrir_pedido')) {
                // Padrão false: mantém o bloqueio atual (sem reabrir/editar pelo app).
                $table->boolean('param_vendas_internas_permitir_reabrir_pedido')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_vendas_internas_permitir_reabrir_pedido')) {
                $table->dropColumn('param_vendas_internas_permitir_reabrir_pedido');
            }
        });
    }
};
