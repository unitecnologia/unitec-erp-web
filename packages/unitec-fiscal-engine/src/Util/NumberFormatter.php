<?php

namespace Unitec\FiscalEngine\Util;

final class NumberFormatter
{
    public static function decimal(float|string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    public static function onlyDigits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
