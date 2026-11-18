<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garante SUPERTEF CREDITO com modo_prazo financeiro (max_parcelas do cadastro).
 * Tabelas de prazo da forma passam a alimentar condições TEF quando modo=tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        $q = DB::table('formas_pagamento')
            ->where('usa_super_tef', true)
            ->whereIn('descricao', ['SUPERTEF CREDITO', 'SUPERTEF CRÉDITO']);

        $row = (clone $q)->first();
        if (! $row) {
            return;
        }

        $upd = [];
        if (Schema::hasColumn('formas_pagamento', 'modo_prazo')) {
            $modo = strtolower(trim((string) ($row->modo_prazo ?? '')));
            $temTabela = Schema::hasTable('tabelas_prazo')
                && DB::table('tabelas_prazo')->where('forma_pagamento_id', $row->id)->exists();
            // Sem tabela cadastrada, financeiro + max_parcelas é a estrutura nativa do cadastro.
            if ($modo === '' || ! in_array($modo, ['financeiro', 'tabela'], true) || ($modo === 'tabela' && ! $temTabela)) {
                $upd['modo_prazo'] = 'financeiro';
            }
        }
        if (Schema::hasColumn('formas_pagamento', 'max_parcelas') && (int) ($row->max_parcelas ?? 0) < 1) {
            $upd['max_parcelas'] = 12;
        }
        if (Schema::hasColumn('formas_pagamento', 'tipo_movimento')) {
            $upd['tipo_movimento'] = 'caixa';
        }
        if (Schema::hasColumn('formas_pagamento', 'aparece_contas_receber')) {
            $upd['aparece_contas_receber'] = false;
        }
        if ($upd !== []) {
            $upd['updated_at'] = now();
            $q->update($upd);
        }
    }

    public function down(): void
    {
        //
    }
};
