<?php

namespace App\Support\Pdv;

use App\Models\Terminal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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

        // Celular de app (Força de Vendas etc.) nunca é caixa NFC-e — evita casar "5" com o id do aparelho.
        if (Schema::hasColumn('terminais', 'categoria_licenca')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('categoria_licenca')->orWhere('categoria_licenca', '!=', 'telefone');
            });
        }

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

            // ERP1 (PDV web) e PDV1 podem ter o mesmo nº lógico: o PDV offline tem preferência.
            $byNumero = (clone $query)
                ->where('numero_logico_terminal', $numero)
                ->when(
                    Schema::hasColumn('terminais', 'origens_dispositivo'),
                    fn (Builder $q) => $q->orderByRaw("CASE WHEN origens_dispositivo LIKE '%pdv_offline%' THEN 0 ELSE 1 END")
                )
                ->orderBy('id')
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
