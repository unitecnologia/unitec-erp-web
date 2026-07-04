<?php

namespace App\Support\Erp;

final class ErpMoney
{
    public static function parseBr(mixed $value, int $decimals = 2): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, $decimals);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return 0.0;
        }

        if (is_numeric($normalized) && ! str_contains($normalized, ',')) {
            return round((float) $normalized, $decimals);
        }

        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, $decimals);
    }

    public static function formatBr(float|int|string|null $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return $decimals > 0 ? '0,' . str_repeat('0', $decimals) : '0';
        }

        $number = is_numeric($value) && ! is_string($value)
            ? (float) $value
            : self::parseBr($value, $decimals);

        return number_format($number, $decimals, ',', '');
    }
}
