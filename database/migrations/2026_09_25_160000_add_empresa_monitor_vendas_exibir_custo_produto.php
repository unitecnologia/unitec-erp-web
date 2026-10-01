<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag: exibir coluna de custo unitário nas grades da Tela de Venda e do Monitor.
 * Default false — clientes atuais sem mudança visual nem query extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_custo_produto')) {
                $table->boolean('param_monitor_vendas_exibir_custo_produto')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_custo_produto')) {
                $table->dropColumn('param_monitor_vendas_exibir_custo_produto');
            }
        });
    }
};
