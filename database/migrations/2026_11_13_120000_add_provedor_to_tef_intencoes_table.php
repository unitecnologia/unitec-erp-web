<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multiprovedor TEF: identifica o provedor de cada intenção sem alterar linhas antigas.
 * Padrão supertef — Scope só quando explicitamente gravado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        Schema::table('tef_intencoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('tef_intencoes', 'provedor')) {
                $table->string('provedor', 20)->default('supertef')->after('requer_reconciliacao');
            }
            if (! Schema::hasColumn('tef_intencoes', 'provider_external_id')) {
                $table->string('provider_external_id', 80)->nullable()->after('payment_uniqueid');
            }
        });

        if (Schema::hasColumn('tef_intencoes', 'provedor')) {
            DB::table('tef_intencoes')
                ->whereNull('provedor')
                ->orWhere('provedor', '')
                ->update(['provedor' => 'supertef']);
        }

        Schema::table('tef_intencoes', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            // Índice composto leve para isolamento empresa/terminal/provedor.
            try {
                $table->index(['empresa_id', 'terminal_id', 'provedor', 'status'], 'tef_intencoes_emp_term_prov_status_idx');
            } catch (\Throwable) {
                // Índice já existe em reexecução parcial.
            }
            unset($sm);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        Schema::table('tef_intencoes', function (Blueprint $table): void {
            try {
                $table->dropIndex('tef_intencoes_emp_term_prov_status_idx');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('tef_intencoes', 'provider_external_id')) {
                $table->dropColumn('provider_external_id');
            }
            if (Schema::hasColumn('tef_intencoes', 'provedor')) {
                $table->dropColumn('provedor');
            }
        });
    }
};
