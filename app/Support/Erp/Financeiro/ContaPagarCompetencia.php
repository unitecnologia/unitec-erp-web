<?php

namespace App\Support\Erp\Financeiro;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Competência DRE de Contas a Pagar: entrada/exibição MM/AAAA;
 * persistência em DATE como primeiro dia do mês (Y-m-01).
 * Não presume vencimento nem pagamento.
 */
final class ContaPagarCompetencia
{
    private const ANO_MIN = 1990;

    private const ANO_MAX = 2100;

    /** Exibe "11/2026" a partir de DATE/Carbon/string; vazio → "". */
    public static function formatMesAno(mixed $raw): string
    {
        $iso = self::toDateString($raw, strict: false);
        if ($iso === null) {
            return '';
        }

        return Carbon::createFromFormat('Y-m-d', $iso)->format('m/Y');
    }

    /**
     * Normaliza para Y-m-01 ou null.
     * Aceita: "", "11/2026", "112026", "2026-11", "2026-11-03", "03/11/2026", Carbon/DateTime.
     * Com strict=true, valor inválido lança; com false, retorna null.
     */
    public static function toDateString(mixed $raw, bool $strict = true): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($raw instanceof \DateTimeInterface) {
            try {
                return self::montarPrimeiroDia((int) $raw->format('n'), (int) $raw->format('Y'));
            } catch (InvalidArgumentException $e) {
                if ($strict) {
                    throw $e;
                }

                return null;
            }
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            if (preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $raw, $m) === 1) {
                return self::montarPrimeiroDia((int) $m[1], (int) $m[2]);
            }

            // Digitação sem barra: 112026 → 11/2026 (não reinterpretar como outra data).
            if (preg_match('/^(\d{2})(\d{4})$/', $raw, $m) === 1) {
                return self::montarPrimeiroDia((int) $m[1], (int) $m[2]);
            }

            if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m) === 1) {
                return self::montarPrimeiroDia((int) $m[2], (int) $m[1]);
            }

            if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])-(\d{2})(?:[ T].*)?$/', $raw, $m) === 1) {
                return self::montarPrimeiroDia((int) $m[2], (int) $m[1]);
            }

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
                // d/m/Y legado: usa o mês/ano informados (não o dia).
                return self::montarPrimeiroDia((int) $m[2], (int) $m[3]);
            }
        } catch (InvalidArgumentException $e) {
            if ($strict) {
                throw $e;
            }

            return null;
        }

        if ($strict) {
            throw new InvalidArgumentException('Competência inválida. Use MM/AAAA (ex.: 11/2026).');
        }

        return null;
    }

    public static function isValidMesAno(mixed $raw): bool
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return true;
        }

        try {
            self::toDateString($raw, strict: true);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function montarPrimeiroDia(int $mes, int $ano): string
    {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mês da competência deve ser entre 01 e 12.');
        }
        if ($ano < self::ANO_MIN || $ano > self::ANO_MAX) {
            throw new InvalidArgumentException('Ano da competência inválido.');
        }

        return sprintf('%04d-%02d-01', $ano, $mes);
    }
}
