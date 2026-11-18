<?php

namespace App\Support\Erp\Tef;

/**
 * Aritmética monetária decimal (scale 2) sem float.
 */
final class TefMoney
{
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_int($value)) {
            return number_format($value, 2, '.', '');
        }

        if (is_float($value)) {
            // Entrada legacy: normaliza via string formatada, não usa em cadeia de cálculo.
            return number_format($value, 2, '.', '');
        }

        $s = trim((string) $value);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }

        if (! is_numeric($s)) {
            return '0.00';
        }

        return bcadd($s, '0', 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), 2);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), 2);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), 2);
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }
}
