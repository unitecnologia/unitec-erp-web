<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag: exibir botão “Mais opções” na Tela de Venda e no Monitor.
 * Default false — clientes atuais sem mudança visual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_mais_opcoes')) {
                $table->boolean('param_monitor_vendas_exibir_mais_opcoes')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_mais_opcoes')) {
                $table->dropColumn('param_monitor_vendas_exibir_mais_opcoes');
            }
        });
    }
};
