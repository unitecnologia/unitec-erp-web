<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regularização fiscal (NFC-e de vendas sem documento): o registro fiscal de vendas
 * vindas da Tela de Venda / Força de Vendas / pedidos não pertence a nenhum caixa PDV.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasColumn('pdv_vendas', 'pdv_caixa_sessao_id')) {
            return;
        }

        Schema::table('pdv_vendas', function (Blueprint $table): void {
            $table->unsignedBigInteger('pdv_caixa_sessao_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Mantém nullable: registros de regularização fiscal ficam sem caixa.
    }
};
