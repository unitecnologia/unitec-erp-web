<?php

declare(strict_types=1);

$dir = dirname(__DIR__) . '/public/images/pwa';

if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

function drawIcon(int $size, bool $maskable = false): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    $blue = imagecolorallocate($img, 15, 52, 96);
    $green = imagecolorallocate($img, 13, 122, 62);
    $white = imagecolorallocate($img, 255, 255, 255);

    imagefilledrectangle($img, 0, 0, $size, $size, $blue);

    $padding = $maskable ? (int) round($size * 0.1) : (int) round($size * 0.125);
    $logoSize = $maskable ? (int) round($size * 0.45) : (int) round($size * 0.3125);
    $logoX = $maskable ? (int) round(($size - $logoSize) / 2) : $padding;
    $logoY = $maskable ? (int) round(($size - $logoSize) / 2) : $padding;

    imagefilledrectangle($img, $logoX, $logoY, $logoX + $logoSize, $logoY + $logoSize, $green);

    $font = 5;
    $char = 'U';
    $textWidth = imagefontwidth($font) * strlen($char);
    $textHeight = imagefontheight($font);
    $textX = (int) ($logoX + ($logoSize - $textWidth) / 2);
    $textY = (int) ($logoY + ($logoSize - $textHeight) / 2);
    imagestring($img, $font, $textX, $textY, $char, $white);

    if (! $maskable && $size >= 192) {
        $label = 'UNI SISTEMAS';
        $labelWidth = imagefontwidth($font) * strlen($label);
        $labelX = (int) (($size - $labelWidth) / 2);
        $labelY = (int) ($logoY + $logoSize + ($size * 0.08));
        imagestring($img, $font, $labelX, $labelY, $label, $white);
    }

    return $img;
}

foreach ([
    'icon-192.png' => [192, false],
    'icon-512.png' => [512, false],
    'icon-maskable-512.png' => [512, true],
] as $filename => [$size, $maskable]) {
    $img = drawIcon($size, $maskable);
    imagepng($img, $dir . '/' . $filename);
    imagedestroy($img);
    echo "Created {$filename}\n";
}
