<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\DB;

/**
 * Sequência global e transacional do número oficial de pedido/venda (vendas.numero).
 */
final class VendaNumeroService
{
    private const CHAVE_GLOBAL = 'global';

    public function proximo(): string
    {
        return DB::transaction(function (): string {
            $sequencia = DB::table('venda_numero_sequencias')
                ->where('chave', self::CHAVE_GLOBAL)
                ->lockForUpdate()
                ->first();

            if ($sequencia === null) {
                $sequencia = $this->inicializarSequencia();
            }

            $proximo = ((int) $sequencia->ultimo_numero) + 1;

            DB::table('venda_numero_sequencias')
                ->where('chave', self::CHAVE_GLOBAL)
                ->update([
                    'ultimo_numero' => $proximo,
                    'updated_at' => now(),
                ]);

            return str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
        });
    }

    private function inicializarSequencia(): object
    {
        $max = DB::table('vendas')
            ->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max() ?? 0;

        DB::table('venda_numero_sequencias')->insert([
            'chave' => self::CHAVE_GLOBAL,
            'ultimo_numero' => $max,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) [
            'chave' => self::CHAVE_GLOBAL,
            'ultimo_numero' => $max,
        ];
    }
}
