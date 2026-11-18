<?php

use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C5B.1 multiprovedor — coluna provedor (supertef/scope).
 * Aditivo: preserva destinos existentes como SuperTEF. Não ativa Scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_liquidacao_destinos')) {
            return;
        }

        if (! Schema::hasColumn('tef_liquidacao_destinos', 'provedor')) {
            Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
                $table->string('provedor', 20)
                    ->default(TefProviderId::SUPERTEF)
                    ->after('terminal_id');
            });
        }

        DB::table('tef_liquidacao_destinos')
            ->whereNull('provedor')
            ->orWhere('provedor', '')
            ->update(['provedor' => TefProviderId::SUPERTEF]);

        $this->dropIndexIfExists('tef_liquidacao_destinos', 'tef_liq_dest_emp_term_acq_uq');
        $this->dropIndexIfExists('tef_liquidacao_destinos', 'tef_liq_dest_emp_term_prov_acq_uq');

        Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->unique(
                ['empresa_id', 'terminal_id', 'provedor', 'acquirer_cnpj'],
                'tef_liq_dest_emp_term_prov_acq_uq'
            );
            $table->index(
                ['empresa_id', 'terminal_id', 'provedor', 'ativo'],
                'tef_liq_dest_emp_term_prov_ativo_idx'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tef_liquidacao_destinos')) {
            return;
        }

        $this->dropIndexIfExists('tef_liquidacao_destinos', 'tef_liq_dest_emp_term_prov_acq_uq');
        $this->dropIndexIfExists('tef_liquidacao_destinos', 'tef_liq_dest_emp_term_prov_ativo_idx');

        if (Schema::hasColumn('tef_liquidacao_destinos', 'provedor')) {
            // Restaura unique anterior (sem provedor) — só se não houver colisão multiprovedor.
            $dup = DB::table('tef_liquidacao_destinos')
                ->select('empresa_id', 'terminal_id', 'acquirer_cnpj', DB::raw('COUNT(*) as c'))
                ->groupBy('empresa_id', 'terminal_id', 'acquirer_cnpj')
                ->having('c', '>', 1)
                ->exists();

            Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
                $table->dropColumn('provedor');
            });

            if (! $dup) {
                Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
                    $table->unique(
                        ['empresa_id', 'terminal_id', 'acquirer_cnpj'],
                        'tef_liq_dest_emp_term_acq_uq'
                    );
                });
            }
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);
            foreach ($indexes as $index) {
                if (($index['name'] ?? '') === $indexName) {
                    Schema::table($table, function (Blueprint $blueprint) use ($indexName, $index): void {
                        if (! empty($index['unique'])) {
                            $blueprint->dropUnique($indexName);
                        } else {
                            $blueprint->dropIndex($indexName);
                        }
                    });

                    return;
                }
            }
        } catch (\Throwable) {
            // Índice ausente — segue.
        }
    }
};
