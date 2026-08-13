<?php

declare(strict_types=1);

/**
 * Gera ícones PWA do ERP a partir da logo Unitecnologia (quando possível).
 */
$outDir = dirname(__DIR__).'/public/images/pwa';
$logo = dirname(__DIR__).'/public/img/erp/brand/unitecnologia-logo.png';

if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

function unitecFill(GdImage $img, int $r, int $g, int $b): void
{
    $color = imagecolorallocate($img, $r, $g, $b);
    imagefilledrectangle($img, 0, 0, imagesx($img) - 1, imagesy($img) - 1, $color);
}

function unitecMakeIcon(int $size, string $logoPath, bool $maskable = false): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    unitecFill($img, 15, 52, 96); // #0f3460

    $pad = $maskable ? (int) round($size * 0.18) : (int) round($size * 0.12);

    if (is_file($logoPath)) {
        $src = @imagecreatefrompng($logoPath);
        if ($src instanceof GdImage) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $box = $size - (2 * $pad);
            $scale = min($box / max(1, $sw), $box / max(1, $sh));
            $dw = max(1, (int) round($sw * $scale));
            $dh = max(1, (int) round($sh * $scale));
            $dx = (int) round(($size - $dw) / 2);
            $dy = (int) round(($size - $dh) / 2);
            imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
            imagedestroy($src);

            return $img;
        }
    }

    // Fallback: bloco verde + U
    $green = imagecolorallocate($img, 13, 122, 62);
    $white = imagecolorallocate($img, 255, 255, 255);
    $box = (int) round($size * 0.42);
    $x = (int) round(($size - $box) / 2);
    $y = (int) round(($size - $box) / 2);
    imagefilledrectangle($img, $x, $y, $x + $box, $y + $box, $green);
    $font = 5;
    $tw = imagefontwidth($font);
    $th = imagefontheight($font);
    imagestring($img, $font, (int) ($x + ($box - $tw) / 2), (int) ($y + ($box - $th) / 2), 'U', $white);

    return $img;
}

foreach ([
    'icon-192.png' => [192, false],
    'icon-512.png' => [512, false],
    'icon-maskable-512.png' => [512, true],
] as $name => [$size, $maskable]) {
    $img = unitecMakeIcon($size, $logo, $maskable);
    $path = $outDir.'/'.$name;
    imagepng($img, $path, 6);
    imagedestroy($img);
    echo "Created {$name} (".filesize($path)." bytes)\n";
}
