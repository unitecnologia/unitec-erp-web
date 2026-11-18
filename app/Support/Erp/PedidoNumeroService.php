<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sequência global do Nº do DAV/pedido (chave em venda_numero_sequencias: pedido).
 * Substitui o pluck full-table de Pedido::nextNumero por lock + MAX SQL.
 */
final class PedidoNumeroService
{
    public const CHAVE = 'pedido';

    /**
     * Próximo número formatado (6 dígitos). Seguro sob concorrência (lockForUpdate).
     */
    public function proximo(): string
    {
        return DB::transaction(function (): string {
            $agora = now();
            $maxExistente = $this->maxNumeroExistente();

            DB::table('venda_numero_sequencias')->insertOrIgnore([
                'chave' => self::CHAVE,
                'ultimo_numero' => $maxExistente,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            $sequencia = DB::table('venda_numero_sequencias')
                ->where('chave', self::CHAVE)
                ->lockForUpdate()
                ->first();

            if ($sequencia === null) {
                throw new RuntimeException('Não foi possível reservar o número do pedido (DAV).');
            }

            // Reconsulta após o lock: nunca ficar abaixo do máximo já gravado.
            $maxAtual = $this->maxNumeroExistente();
            $proximo = max((int) $sequencia->ultimo_numero, $maxAtual) + 1;

            DB::table('venda_numero_sequencias')
                ->where('chave', self::CHAVE)
                ->update([
                    'ultimo_numero' => $proximo,
                    'updated_at' => now(),
                ]);

            return str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Maior número já usado em pedidos.
     * Usa o índice único em `numero` (sem pluck / sem CAST full-scan).
     * Números do DAV são zero-padded (6 dígitos) — MAX textual = MAX numérico.
     */
    public function maxNumeroExistente(): int
    {
        $numero = DB::table('pedidos')->max('numero');

        if ($numero === null || $numero === '') {
            return 0;
        }

        return max(0, (int) preg_replace('/\D/', '', (string) $numero));
    }
}
