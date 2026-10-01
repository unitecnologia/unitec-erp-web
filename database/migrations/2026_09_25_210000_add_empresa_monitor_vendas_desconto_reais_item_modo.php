<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo do desconto em R$ do item no app Força de Vendas.
 * unitario = valor por unidade (comportamento atual). linha = valor único na linha.
 * Pedidos já gravados não são recalculados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_desconto_reais_item_modo')) {
                $table->string('param_monitor_vendas_desconto_reais_item_modo', 20)->default('unitario');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_desconto_reais_item_modo')) {
                $table->dropColumn('param_monitor_vendas_desconto_reais_item_modo');
            }
        });
    }
};
