<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula a sessão PDV à conta de caixa tipo PDV (tela Caixa → CAIXA PDV n).
 * Sessões antigas: caixa PDV padrão do operador na empresa; havendo um só caixa PDV, ele.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_caixa_sessoes') || ! Schema::hasTable('caixa_contas')) {
            return;
        }

        if (! Schema::hasColumn('pdv_caixa_sessoes', 'caixa_conta_id')) {
            Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
                $table->foreignId('caixa_conta_id')
                    ->nullable()
                    ->after('terminal_id')
                    ->constrained('caixa_contas')
                    ->nullOnDelete();
            });
        }

        $contasPdv = DB::table('caixa_contas')
            ->where('ativo', true)
            ->where(fn ($q) => $q->where('sistema', false)->orWhereNull('sistema'))
            ->whereRaw('UPPER(nome) <> ?', ['CAIXA GERAL'])
            ->whereIn('tipo', ['PDV', 'CAIXA', 'X'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($contasPdv === []) {
            return;
        }

        $padroes = Schema::hasTable('caixa_conta_user')
            ? DB::table('caixa_conta_user')
                ->where('is_padrao', true)
                ->whereIn('caixa_conta_id', $contasPdv)
                ->get(['user_id', 'empresa_id', 'caixa_conta_id'])
                ->mapWithKeys(fn (object $row): array => [
                    $row->user_id.':'.$row->empresa_id => (int) $row->caixa_conta_id,
                ])
                ->all()
            : [];

        $unica = count($contasPdv) === 1 ? $contasPdv[0] : null;

        DB::table('pdv_caixa_sessoes')
            ->whereNull('caixa_conta_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'empresa_id'])
            ->each(function (object $sessao) use ($padroes, $unica): void {
                $contaId = $padroes[$sessao->user_id.':'.$sessao->empresa_id] ?? $unica;

                if ($contaId) {
                    DB::table('pdv_caixa_sessoes')
                        ->where('id', $sessao->id)
                        ->update(['caixa_conta_id' => $contaId]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('pdv_caixa_sessoes') && Schema::hasColumn('pdv_caixa_sessoes', 'caixa_conta_id')) {
            Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('caixa_conta_id');
            });
        }
    }
};
