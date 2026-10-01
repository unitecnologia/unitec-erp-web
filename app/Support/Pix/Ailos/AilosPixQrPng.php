<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use RuntimeException;

/** Gera PNG Base64 do BR Code sem dependência simplesoftwareio (usa bacon/bacon-qr-code do ERP). */
final class AilosPixQrPng
{
    public static function toBase64(string $brCode, int $size = 512): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Extensão GD necessária para gerar QR Code Pix.');
        }

        $brCode = trim($brCode);
        if ($brCode === '') {
            throw new RuntimeException('BR Code Pix vazio.');
        }

        $matrix = Encoder::encode($brCode, ErrorCorrectionLevel::M())->getMatrix();
        $modules = max(1, $matrix->getWidth());
        $quiet = 2;
        $modulePx = max(2, (int) floor($size / ($modules + (2 * $quiet))));
        $imgSize = ($modules * $modulePx) + (2 * $quiet * $modulePx);

        $img = imagecreate($imgSize, $imgSize);
        if ($img === false) {
            throw new RuntimeException('Falha ao criar imagem do QR Pix.');
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        $offset = $quiet * $modulePx;
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }
                $px = $offset + ($x * $modulePx);
                $py = $offset + ($y * $modulePx);
                imagefilledrectangle($img, $px, $py, $px + $modulePx - 1, $py + $modulePx - 1, $black);
            }
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        if ($png === '') {
            throw new RuntimeException('Falha ao codificar PNG do QR Pix.');
        }

        return base64_encode($png);
    }
}
