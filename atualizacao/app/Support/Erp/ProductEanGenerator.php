<?php

namespace App\Support\Erp;

class ProductEanGenerator
{
    public static function generate(int $codigo): string
    {
        $base = '777' . str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return $base . self::checkDigit($base);
    }

    protected static function checkDigit(string $code12): string
    {
        $sumEven = 0;
        $sumOdd = 0;

        for ($i = 1; $i <= 12; $i++) {
            $digit = (int) $code12[$i - 1];

            if ($i % 2 === 0) {
                $sumEven += $digit;
            } else {
                $sumOdd += $digit;
            }
        }

        $sumEven *= 3;
        $total = $sumEven + $sumOdd;
        $next = (int) (ceil($total / 10) * 10);

        return (string) ($next - $total);
    }
}
