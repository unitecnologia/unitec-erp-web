<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo exato título a receber ↔ venda PDV.
 * O documento PDV-NNNNNN se repete entre sessões de caixa e não identifica a venda sozinho.
 * Backfill só preenche títulos com uma única venda candidata (mesmo número, empresa e horário);
 * ambíguos permanecem nulos e são tratados pelo fallback restrito do PdvVendaFinanceiroService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contas_receber')) {
            return;
        }

        if (! Schema::hasColumn('contas_receber', 'pdv_venda_id')) {
            Schema::table('contas_receber', function (Blueprint $table): void {
                $table->foreignId('pdv_venda_id')->nullable()->after('documento')
                    ->constrained('pdv_vendas')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contas_receber', 'pdv_venda_id')) {
            return;
        }

        Schema::table('contas_receber', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pdv_venda_id');
        });
    }

    private function backfill(): void
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasTable('pdv_caixa_sessoes')) {
            return;
        }

        DB::table('contas_receber')
            ->select(['id', 'empresa_id', 'documento', 'created_at'])
            ->whereNull('pdv_venda_id')
            ->where('documento', 'like', 'PDV-%')
            ->whereNotNull('created_at')
            ->orderBy('id')
            ->chunkById(500, function ($contas): void {
                foreach ($contas as $conta) {
                    if (! preg_match('/^PDV-(\d+)(?:\/\d+)?$/', (string) $conta->documento, $m)) {
                        continue;
                    }

                    $criado = strtotime((string) $conta->created_at);
                    if ($criado === false) {
                        continue;
                    }

                    $janela = [date('Y-m-d H:i:s', $criado - 600), date('Y-m-d H:i:s', $criado + 600)];

                    $candidatas = DB::table('pdv_vendas as v')
                        ->leftJoin('pdv_caixa_sessoes as s', 's.id', '=', 'v.pdv_caixa_sessao_id')
                        ->where('v.numero', (int) $m[1])
                        ->where(function ($q) use ($conta): void {
                            $conta->empresa_id === null
                                ? $q->whereNull('s.empresa_id')
                                : $q->where('s.empresa_id', $conta->empresa_id);
                        })
                        ->where(function ($q) use ($janela): void {
                            $q->whereBetween('v.fechado_em', $janela)
                                ->orWhereBetween('v.created_at', $janela);
                        })
                        ->limit(2)
                        ->pluck('v.id');

                    if ($candidatas->count() === 1) {
                        DB::table('contas_receber')
                            ->where('id', $conta->id)
                            ->update(['pdv_venda_id' => (int) $candidatas->first()]);
                    }
                }
            });
    }
};
