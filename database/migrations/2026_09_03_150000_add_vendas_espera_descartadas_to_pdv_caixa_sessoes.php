<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_caixa_sessoes') || Schema::hasColumn('pdv_caixa_sessoes', 'vendas_espera_descartadas')) {
            return;
        }

        Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->json('vendas_espera_descartadas')->nullable()->after('itens_cancelados');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_caixa_sessoes') || ! Schema::hasColumn('pdv_caixa_sessoes', 'vendas_espera_descartadas')) {
            return;
        }

        Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->dropColumn('vendas_espera_descartadas');
        });
    }
};
