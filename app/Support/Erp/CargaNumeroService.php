<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sequência por empresa do Nº da carga (chave em venda_numero_sequencias: carga:{empresaId}).
 * O número definitivo só deve ser consumido dentro da transaction do saveCarga.
 */
final class CargaNumeroService
{
    public static function chave(int $empresaId): string
    {
        return 'carga:'.$empresaId;
    }

    /**
     * Próximo número definitivo. Exige transaction aberta (rollback desfaz o avanço).
     */
    public function proximo(int $empresaId): string
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa inválida para numeração de carga.');
        }

        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('A sequência de carga só pode ser consumida dentro de uma transação.');
        }

        $chave = self::chave($empresaId);
        $agora = now();
        $maxExistente = $this->maxNumeroExistente($empresaId);

        DB::table('venda_numero_sequencias')->insertOrIgnore([
            'chave' => $chave,
            'ultimo_numero' => $maxExistente,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $sequencia = DB::table('venda_numero_sequencias')
            ->where('chave', $chave)
            ->lockForUpdate()
            ->first();

        if ($sequencia === null) {
            throw new RuntimeException('Não foi possível reservar o número da carga.');
        }

        // Reconsulta após o lock: nunca ficar abaixo do máximo já gravado (inclui canceladas).
        $maxAtual = $this->maxNumeroExistente($empresaId);
        $proximo = max((int) $sequencia->ultimo_numero, $maxAtual) + 1;

        DB::table('venda_numero_sequencias')
            ->where('chave', $chave)
            ->update([
                'ultimo_numero' => $proximo,
                'updated_at' => now(),
            ]);

        return (string) $proximo;
    }

    /**
     * Maior número já usado em cargas da empresa (qualquer status). Consulta leve para preview/init.
     */
    public function maxNumeroExistente(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $expr = $this->maxNumeroExpression();

        $max = DB::table('cargas')
            ->where('empresa_id', $empresaId)
            ->selectRaw("{$expr} as max_numero")
            ->value('max_numero');

        return max(0, (int) ($max ?? 0));
    }

    private function maxNumeroExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'MAX(CAST(numero AS INTEGER))'
            : 'MAX(CAST(numero AS UNSIGNED))';
    }
}
