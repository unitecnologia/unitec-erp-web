<?php

namespace App\Support\Erp\Boleto;

/**
 * Código de barras Interleaved 2 of 5 (padrão FEBRABAN para boletos).
 * Gera PNG (DomPDF) via GD.
 */
final class BoletoInterleaved2of5
{
    /** @var array<string, string> n=narrow, w=wide — padrão de barras por dígito */
    private const PATTERNS = [
        '0' => 'nnwwn',
        '1' => 'wnnnw',
        '2' => 'nwnnw',
        '3' => 'wwnnn',
        '4' => 'nnwnw',
        '5' => 'wnwnn',
        '6' => 'nwwnn',
        '7' => 'nnnww',
        '8' => 'wnnwn',
        '9' => 'nwnwn',
    ];

    /**
     * Data URI PNG do código de barras (44 dígitos do boleto).
     */
    public static function dataUri(?string $codigoBarras, int $barHeight = 50, int $module = 2): ?string
    {
        $png = self::png($codigoBarras, $barHeight, $module);
        if ($png === null) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public static function png(?string $codigoBarras, int $barHeight = 50, int $module = 2): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagepng')) {
            return null;
        }

        $modules = self::modules($codigoBarras);
        if ($modules === null || $modules === []) {
            return null;
        }

        $module = max(1, $module);
        $barHeight = max(20, $barHeight);
        $width = count($modules) * $module;
        $img = imagecreatetruecolor($width, $barHeight);
        if ($img === false) {
            return null;
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        if ($white === false || $black === false) {
            imagedestroy($img);

            return null;
        }

        imagefilledrectangle($img, 0, 0, $width, $barHeight, $white);

        $x = 0;
        foreach ($modules as $isBar) {
            if ($isBar) {
                imagefilledrectangle($img, $x, 0, $x + $module - 1, $barHeight - 1, $black);
            }
            $x += $module;
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * @return list<bool>|null true = barra preta (módulo)
     */
    private static function modules(?string $codigoBarras): ?array
    {
        $digits = preg_replace('/\D/', '', (string) $codigoBarras) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) % 2 === 1) {
            $digits = '0'.$digits;
        }

        $narrow = 1;
        $wide = 3;
        $modules = [];

        $push = static function (string $pattern, bool $asBar) use (&$modules, $narrow, $wide): void {
            foreach (str_split($pattern) as $ch) {
                $w = $ch === 'w' ? $wide : $narrow;
                for ($i = 0; $i < $w; $i++) {
                    $modules[] = $asBar;
                }
            }
        };

        // Start: nnnn (bar,space,bar,space)
        $push('nn', true);
        $push('nn', false);

        for ($i = 0, $len = strlen($digits); $i < $len; $i += 2) {
            $barPattern = self::PATTERNS[$digits[$i]] ?? null;
            $spacePattern = self::PATTERNS[$digits[$i + 1]] ?? null;
            if ($barPattern === null || $spacePattern === null) {
                return null;
            }

            for ($p = 0; $p < 5; $p++) {
                $bw = ($barPattern[$p] ?? 'n') === 'w' ? $wide : $narrow;
                $sw = ($spacePattern[$p] ?? 'n') === 'w' ? $wide : $narrow;
                for ($b = 0; $b < $bw; $b++) {
                    $modules[] = true;
                }
                for ($s = 0; $s < $sw; $s++) {
                    $modules[] = false;
                }
            }
        }

        // Stop: wnn
        $push('w', true);
        $push('n', false);
        $push('n', true);

        return $modules;
    }
}
