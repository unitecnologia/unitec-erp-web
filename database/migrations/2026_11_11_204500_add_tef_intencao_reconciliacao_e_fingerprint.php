<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impede reuso automático de pagamento SuperTEF pago sem pedido em venda nova.
 * - cupom_fingerprint: vínculo com o cupom da operação original
 * - requer_reconciliacao: órfão administrativo (não ocupa POS / não auto-vincula)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        if (! Schema::hasColumn('tef_intencoes', 'cupom_fingerprint')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->string('cupom_fingerprint', 64)->nullable()->after('order_id');
            });
        }

        if (! Schema::hasColumn('tef_intencoes', 'requer_reconciliacao')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->boolean('requer_reconciliacao')->default(false)->after('pdv_venda_pagamento_id');
            });
        }

        // Intenção paga órfã conhecida (id=4): sem cupom persistido — só reconciliação admin.
        DB::table('tef_intencoes')
            ->where('id', 4)
            ->where('status', 'paga')
            ->whereNull('pdv_venda_id')
            ->update([
                'requer_reconciliacao' => 1,
                'payment_message' => 'RECONCILIAR: pago sem pedido vinculado (sem fingerprint do cupom). Não usar em venda nova.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        if (Schema::hasColumn('tef_intencoes', 'requer_reconciliacao')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->dropColumn('requer_reconciliacao');
            });
        }

        if (Schema::hasColumn('tef_intencoes', 'cupom_fingerprint')) {
            Schema::table('tef_intencoes', function (Blueprint $table): void {
                $table->dropColumn('cupom_fingerprint');
            });
        }
    }
};
