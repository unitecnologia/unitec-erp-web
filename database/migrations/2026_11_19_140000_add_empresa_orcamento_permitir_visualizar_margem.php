<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag: permitir visualizar margem/custo no Lançamento de Orçamento (Mais opções).
 * Default false — sem mudança visual nem query extra para clientes atuais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_orcamento_permitir_visualizar_margem')) {
                $table->boolean('param_orcamento_permitir_visualizar_margem')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_orcamento_permitir_visualizar_margem')) {
                $table->dropColumn('param_orcamento_permitir_visualizar_margem');
            }
        });
    }
};
