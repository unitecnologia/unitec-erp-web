<?php

namespace App\Support\Erp\Orcamento;

class OrcamentoBobinaFormatter
{
    public const COLS = 48;

    public static function rule(string $char = '-'): string
    {
        return str_repeat($char, self::COLS);
    }

    public static function center(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $length = mb_strlen($text, 'UTF-8');

        if ($length >= self::COLS) {
            return $text;
        }

        $padding = (int) floor((self::COLS - $length) / 2);

        return str_repeat(' ', $padding) . $text;
    }

    /**
     * @return list<string>
     */
    public static function wrap(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [''];
        }

        $lines = [];

        while (mb_strlen($text, 'UTF-8') > self::COLS) {
            $slice = mb_substr($text, 0, self::COLS, 'UTF-8');
            $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');

            if ($lastSpace !== false && $lastSpace > 8) {
                $lines[] = rtrim(mb_substr($text, 0, $lastSpace, 'UTF-8'));
                $text = ltrim(mb_substr($text, $lastSpace, null, 'UTF-8'));
            } else {
                $lines[] = $slice;
                $text = ltrim(mb_substr($text, self::COLS, null, 'UTF-8'));
            }
        }

        if ($text !== '') {
            $lines[] = $text;
        }

        return $lines;
    }

    public static function line(string $left, ?string $right = null): string
    {
        $left = rtrim($left);

        if ($right === null || $right === '') {
            return self::wrap($left)[0] ?? '';
        }

        $right = ltrim($right);
        $space = self::COLS - mb_strlen($left, 'UTF-8') - mb_strlen($right, 'UTF-8');

        if ($space < 1) {
            return $left . ' ' . $right;
        }

        return $left . str_repeat(' ', $space) . $right;
    }

    public static function padLeft(string $text, int $width): string
    {
        $length = mb_strlen($text, 'UTF-8');

        if ($length >= $width) {
            return $text;
        }

        return str_repeat(' ', $width - $length) . $text;
    }

    public static function padRight(string $text, int $width): string
    {
        $length = mb_strlen($text, 'UTF-8');

        if ($length >= $width) {
            return mb_substr($text, 0, $width, 'UTF-8');
        }

        return $text . str_repeat(' ', $width - $length);
    }

    public static function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}
