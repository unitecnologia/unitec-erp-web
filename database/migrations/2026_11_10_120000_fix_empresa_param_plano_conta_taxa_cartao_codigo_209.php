<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A base padrão apontava param_plano_conta_taxa_cartao_id para o plano id=13
 * (código 208 IMPOSTOS/TAXAS). O padrão oficial é código 209 (TARIFAS BANCÁRIAS).
 * Corrige apenas empresas cujo vínculo atual não é o código 209.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('empresas')
            || ! Schema::hasTable('planos_contas')
            || ! Schema::hasColumn('empresas', 'param_plano_conta_taxa_cartao_id')
        ) {
            return;
        }

        $taxaId = DB::table('planos_contas')
            ->where('codigo', 209)
            ->where('dc', 'D')
            ->where('ativo', true)
            ->value('id');

        if (! $taxaId) {
            return;
        }

        // Só corrige vínculo existente apontando para plano errado/inexistente.
        // Não preenche NULL (cliente pode deixar taxa sem padrão de propósito).
        $empresaIds = DB::table('empresas as e')
            ->leftJoin('planos_contas as p', 'p.id', '=', 'e.param_plano_conta_taxa_cartao_id')
            ->whereNotNull('e.param_plano_conta_taxa_cartao_id')
            ->where(function ($query): void {
                $query->whereNull('p.id')
                    ->orWhere('p.codigo', '<>', 209);
            })
            ->pluck('e.id');

        if ($empresaIds->isEmpty()) {
            return;
        }

        DB::table('empresas')
            ->whereIn('id', $empresaIds->all())
            ->update(['param_plano_conta_taxa_cartao_id' => (int) $taxaId]);
    }

    public function down(): void
    {
        // Correção de dado — sem reversão automática.
    }
};
