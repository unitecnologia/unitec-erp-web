<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\DB;

/**
 * Sequência transacional do número de Contas a Receber (chave em venda_numero_sequencias).
 *
 * Substitui o antigo ContaReceber::nextNumero() baseado em pluck+regex de toda a tabela,
 * que competia no UNIQUE(numero) e podia prender a TX de faturamento (PROMISSÓRIA/parcelas).
 */
final class ContaReceberNumeroService
{
    private const CHAVE = 'contas_receber';

    public function proximo(): string
    {
        return $this->reservar(1)[0];
    }

    /**
     * Reserva N números consecutivos com um único lock (ex.: 7 parcelas).
     *
     * @return list<string>
     */
    public function reservar(int $quantidade): array
    {
        $quantidade = max(0, $quantidade);
        if ($quantidade === 0) {
            return [];
        }

        $run = function () use ($quantidade): array {
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
                throw new \RuntimeException('Não foi possível reservar o número da conta a receber.');
            }

            $maxAtual = $this->maxNumeroExistente();
            $base = max((int) $sequencia->ultimo_numero, $maxAtual);

            $numeros = [];
            for ($i = 1; $i <= $quantidade; $i++) {
                $numeros[] = str_pad((string) ($base + $i), 6, '0', STR_PAD_LEFT);
            }

            DB::table('venda_numero_sequencias')
                ->where('chave', self::CHAVE)
                ->update([
                    'ultimo_numero' => $base + $quantidade,
                    'updated_at' => now(),
                ]);

            return $numeros;
        };

        if (DB::transactionLevel() > 0) {
            return $run();
        }

        return DB::transaction($run);
    }

    public function maxNumeroExistente(): int
    {
        try {
            $driver = DB::connection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $max = DB::table('contas_receber')
                    ->selectRaw("MAX(CAST(REGEXP_REPLACE(COALESCE(numero, ''), '[^0-9]', '') AS UNSIGNED)) as max_num")
                    ->value('max_num');

                return max(0, (int) ($max ?? 0));
            }
        } catch (\Throwable) {
            // SQLite / driver antigo: fallback PHP.
        }

        $max = DB::table('contas_receber')
            ->pluck('numero')
            ->map(static fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return max(0, (int) ($max ?? 0));
    }
}
