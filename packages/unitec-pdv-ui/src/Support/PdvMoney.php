<?php

namespace Unitec\PdvUi\Support;

/**
 * Helpers de moeda BR usados pelas views compartilhadas do PDV (ERP + offline).
 * Mantém as blades livres de `App\Support\Erp\*`.
 */
final class PdvMoney
{
    public static function parseBr(mixed $value, int $decimals = 2): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, $decimals);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $raw = str_replace(["\u{00A0}", ' '], '', $raw);
        $raw = str_replace('R$', '', $raw);
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (! is_numeric($raw)) {
            return 0.0;
        }

        return round((float) $raw, $decimals);
    }

    public static function formatBr(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format(self::parseBr($value, $decimals), $decimals, ',', '.');
    }
}
