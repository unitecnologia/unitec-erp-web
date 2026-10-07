<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfes') || ! Schema::hasTable('ordens_servico')) {
            return;
        }

        if (! Schema::hasColumn('nfes', 'ordem_servico_id')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->foreignId('ordem_servico_id')
                    ->nullable()
                    ->after('devolucao_compra_id')
                    ->constrained('ordens_servico')
                    ->nullOnDelete();
                $table->index(['ordem_servico_id', 'status'], 'nfes_ordem_servico_status_index');
            });
        }

        $this->vincularNfesJaEmitidas();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('nfes', 'ordem_servico_id')) {
            return;
        }

        Schema::table('nfes', function (Blueprint $table): void {
            $table->dropIndex('nfes_ordem_servico_status_index');
            $table->dropConstrainedForeignId('ordem_servico_id');
        });
    }

    private function vincularNfesJaEmitidas(): void
    {
        DB::table('nfes')
            ->whereNull('ordem_servico_id')
            ->where('obs_contribuinte', 'like', 'NF-E ORIGINADA DA OS N%')
            ->select(['id', 'empresa_id', 'obs_contribuinte'])
            ->orderBy('id')
            ->each(function (object $nfe): void {
                if (! preg_match('/^NF-E ORIGINADA DA OS N\S*\s+(\d+)/u', (string) $nfe->obs_contribuinte, $m)) {
                    return;
                }

                $numero = trim($m[1]);
                $osId = DB::table('ordens_servico')
                    ->when($nfe->empresa_id, fn ($q) => $q->where(fn ($q) => $q
                        ->whereNull('empresa_id')
                        ->orWhere('empresa_id', $nfe->empresa_id)))
                    ->where(fn ($q) => $q
                        ->where('numero', $numero)
                        ->orWhereRaw('TRIM(LEADING \'0\' FROM numero) = ?', [$numero])
                        ->orWhere('codigo_legado', $numero))
                    ->orderByDesc('id')
                    ->value('id');

                if ($osId) {
                    DB::table('nfes')->where('id', $nfe->id)->update(['ordem_servico_id' => $osId]);
                }
            });
    }
};
