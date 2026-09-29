<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * modo_prazo: financeiro | tabela
 *
 * Backfill preserva o comportamento da heurística anterior
 * (isPrazoFinanceiroValido sem modo): max>=1, intervalo>0, excluindo (1,30).
 *
 * NÃO executar automaticamente nesta etapa de implementação — validar com o usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        if (! Schema::hasColumn('formas_pagamento', 'modo_prazo')) {
            Schema::table('formas_pagamento', function (Blueprint $table): void {
                $table->string('modo_prazo', 20)
                    ->default('tabela')
                    ->after('intervalo_parcelas');
            });
        }

        if (! Schema::hasColumn('formas_pagamento', 'modo_prazo')) {
            return;
        }

        DB::table('formas_pagamento')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $max = (int) ($row->max_parcelas ?? 0);
                    $intervalo = (int) ($row->intervalo_parcelas ?? 0);
                    $financeiro = $max >= 1
                        && $intervalo > 0
                        && ! ($max === 1 && $intervalo === 30);

                    DB::table('formas_pagamento')
                        ->where('id', $row->id)
                        ->update([
                            'modo_prazo' => $financeiro ? 'financeiro' : 'tabela',
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        if (! Schema::hasColumn('formas_pagamento', 'modo_prazo')) {
            return;
        }

        Schema::table('formas_pagamento', function (Blueprint $table): void {
            $table->dropColumn('modo_prazo');
        });
    }
};
