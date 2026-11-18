<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciência persistente por intenção: operador já reconheceu aquele órfão
 * e novas vendas não precisam reautorizar o mesmo aviso.
 * Não conclui a reconciliação administrativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        if (! Schema::hasColumn('tef_intencoes', 'ciencia_reconciliacao_em')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->timestamp('ciencia_reconciliacao_em')->nullable()->after('requer_reconciliacao');
            });
        }

        // Órfãos já marcados para recon (ex.: id 4 e 10): operador/admin já os separou —
        // não reexibir o aviso a cada nova venda.
        DB::table('tef_intencoes')
            ->where('status', 'paga')
            ->whereNull('pdv_venda_id')
            ->where('requer_reconciliacao', true)
            ->whereNull('ciencia_reconciliacao_em')
            ->update([
                'ciencia_reconciliacao_em' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        if (Schema::hasColumn('tef_intencoes', 'ciencia_reconciliacao_em')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->dropColumn('ciencia_reconciliacao_em');
            });
        }
    }
};
