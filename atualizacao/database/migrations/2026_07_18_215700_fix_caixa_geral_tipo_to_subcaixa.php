<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CAIXA GERAL é SUBCAIXA (recebe sangria/fechamento do PDV), não tipo PDV.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_contas')) {
            return;
        }

        DB::table('caixa_contas')
            ->whereRaw('UPPER(nome) = ?', ['CAIXA GERAL'])
            ->update(['tipo' => 'SUBCAIXA']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_contas')) {
            return;
        }

        DB::table('caixa_contas')
            ->whereRaw('UPPER(nome) = ?', ['CAIXA GERAL'])
            ->update(['tipo' => 'PDV']);
    }
};
