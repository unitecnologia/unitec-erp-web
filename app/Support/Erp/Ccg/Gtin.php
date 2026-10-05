<?php

namespace App\Support\Erp\Ccg;

final class Gtin
{
    /**
     * @var list<int>
     */
    private const ACCEPTED_LENGTHS = [8, 12, 13, 14];

    public static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isAcceptedLength(string $gtin): bool
    {
        return in_array(strlen($gtin), self::ACCEPTED_LENGTHS, true);
    }

    public static function hasValidCheckDigit(string $gtin): bool
    {
        if (! self::isAcceptedLength($gtin) || ! ctype_digit($gtin)) {
            return false;
        }

        $digits = array_map(intval(...), str_split($gtin));
        $check = array_pop($digits);
        $sum = 0;
        $weight = 3;

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            $sum += $digits[$index] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        $expected = (10 - ($sum % 10)) % 10;

        return $check === $expected;
    }

    /**
     * Prefixo GS1 Brasil (789/790). No GTIN-14 o primeiro dígito é indicador de embalagem.
     */
    public static function isPrefixBrasil(string $gtin): bool
    {
        if (! self::isAcceptedLength($gtin)) {
            return false;
        }

        $prefix = strlen($gtin) === 14
            ? substr($gtin, 1, 3)
            : substr($gtin, 0, 3);

        return $prefix === '789' || $prefix === '790';
    }
}
