<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2.4 — Competência e plano no título CP (independentes de vencimento/pagamento).
 * Não preenche histórico automaticamente. Não altera rotinas de baixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contas_pagar')) {
            return;
        }

        Schema::table('contas_pagar', function (Blueprint $table): void {
            if (! Schema::hasColumn('contas_pagar', 'competencia')) {
                $table->date('competencia')->nullable()->after('vencimento');
                $table->index('competencia', 'contas_pagar_competencia_idx');
            }

            if (! Schema::hasColumn('contas_pagar', 'plano_conta_id')) {
                $table->unsignedBigInteger('plano_conta_id')->nullable()->after('competencia');
                $table->index('plano_conta_id', 'contas_pagar_plano_conta_id_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contas_pagar')) {
            return;
        }

        Schema::table('contas_pagar', function (Blueprint $table): void {
            if (Schema::hasColumn('contas_pagar', 'plano_conta_id')) {
                $table->dropIndex('contas_pagar_plano_conta_id_idx');
                $table->dropColumn('plano_conta_id');
            }
            if (Schema::hasColumn('contas_pagar', 'competencia')) {
                $table->dropIndex('contas_pagar_competencia_idx');
                $table->dropColumn('competencia');
            }
        });
    }
};
