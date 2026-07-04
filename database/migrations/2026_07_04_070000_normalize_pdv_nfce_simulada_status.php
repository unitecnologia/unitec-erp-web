<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce')) {
            return;
        }

        // Cupons finalizados no PDV ficavam com status "simulada" e não apareciam em Transmitidos.
        DB::table('pdv_venda_nfce')
            ->where('status', 'simulada')
            ->where('simulada', true)
            ->whereNotNull('chave')
            ->update(['status' => 'autorizada']);
    }

    public function down(): void
    {
        // irreversível com segurança — registros autorizados simulados não voltam para simulada
    }
};
