<?php

namespace App\Support\Pdv;

use App\Models\Terminal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolve o caixa do PDV offline (PDV1, "1"…) sem comparar numero_logico
 * com a string "PDV1" — no MySQL isso vira 0 e casa com ERP1.
 */
final class PdvOfflineTerminalLookup
{
    public static function find(int $empresaId, string $terminalKey, bool $somenteAtivo = true): ?Terminal
    {
        $terminalKey = trim($terminalKey);

        if ($empresaId < 1 || $terminalKey === '') {
            return null;
        }

        $query = Terminal::query()->where('empresa_id', $empresaId);

        if ($somenteAtivo) {
            $query->where(function (Builder $q): void {
                $q->where('ativo', true)->orWhereNull('ativo');
            });
        }

        $numero = self::extractNumero($terminalKey);

        if ($numero !== null) {
            $nome = 'PDV'.$numero;
            $byNome = (clone $query)
                ->whereRaw('UPPER(TRIM(nome)) = ?', [strtoupper($nome)])
                ->first();

            if ($byNome !== null) {
                return $byNome;
            }

            $byNumero = (clone $query)
                ->where('numero_logico_terminal', $numero)
                ->first();

            if ($byNumero !== null) {
                return $byNumero;
            }
        }

        $byNomeExato = (clone $query)
            ->whereRaw('UPPER(TRIM(nome)) = ?', [strtoupper($terminalKey)])
            ->first();

        if ($byNomeExato !== null) {
            return $byNomeExato;
        }

        if (ctype_digit($terminalKey)) {
            return (clone $query)
                ->where('id', (int) $terminalKey)
                ->first();
        }

        return null;
    }

    public static function extractNumero(string $terminalKey): ?int
    {
        $terminalKey = trim($terminalKey);

        if ($terminalKey === '') {
            return null;
        }

        if (preg_match('/^PDV\s*(\d+)$/i', $terminalKey, $m) === 1) {
            $n = (int) $m[1];

            return $n > 0 ? $n : null;
        }

        if (ctype_digit($terminalKey)) {
            $n = (int) $terminalKey;

            return $n > 0 ? $n : null;
        }

        return null;
    }
}
