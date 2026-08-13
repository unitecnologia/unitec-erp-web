<?php

namespace App\Support\Erp\Printing\EscPos;

/**
 * Codificação de texto para bobina ESC/POS (CP850 — acentos PT-BR).
 */
final class EscPosCharset
{
    /** Tabela Epson/compatível: PC850 Multilingual */
    public const TABLE_PC850 = 2;

    public static function encode(string $utf8): string
    {
        $utf8 = self::normalize($utf8);

        foreach (['CP850//IGNORE', 'IBM850//IGNORE', 'CP860//IGNORE'] as $charset) {
            $converted = @iconv('UTF-8', $charset, $utf8);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        // Fallback sem estragar acento com ASCII//TRANSLIT ('I, ^A)
        $converted = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $utf8);

        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E\r\n\t]/', '?', $utf8) ?? $utf8;
    }

    private static function normalize(string $value): string
    {
        // Corrige caracteres compostos quebrados comuns
        $map = [
            "\u{0301}" => '', // combining acute leftover
            "\u{0302}" => '',
            "\u{0303}" => '',
            '´' => '',
            '`' => '',
        ];

        return strtr($value, $map);
    }
}
