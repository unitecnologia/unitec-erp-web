<?php

namespace App\Support\Erp;

final class BrDecimal
{
    public static function parse(mixed $value, int $decimals = 2): float
    {
        return self::tryParse($value, $decimals) ?? 0.0;
    }

    /**
     * Parse estrito: retorna null quando o valor é inválido (não numérico).
     * Use em fluxos fiscais/XML em vez de aceitar 0 silencioso.
     */
    public static function tryParse(mixed $value, int $decimals = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, $decimals);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (is_numeric($normalized) && ! str_contains($normalized, ',')) {
            return round((float) $normalized, $decimals);
        }

        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, $decimals);
    }
}
