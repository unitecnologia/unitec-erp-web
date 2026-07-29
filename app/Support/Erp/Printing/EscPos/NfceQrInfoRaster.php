<?php

namespace App\Support\Erp\Printing\EscPos;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\GdEscposImage;
use RuntimeException;

/**
 * Monta imagem ESC/POS: QR à esquerda + infos fiscais à direita,
 * ocupando ~toda a largura da bobina 80mm.
 */
final class NfceQrInfoRaster
{
    /** Largura útil aproximada bobina 80mm (203 dpi). */
    private const TARGET_WIDTH = 560;

    /**
     * @param  list<string>  $infoLines
     */
    public static function toEscposImage(string $qrTexto, array $infoLines): EscposImage
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Extensão GD indisponível para montar QR NFC-e.');
        }

        $qrTexto = trim($qrTexto);
        if ($qrTexto === '') {
            throw new RuntimeException('Conteúdo do QR NFC-e vazio.');
        }

        $matrix = Encoder::encode($qrTexto, ErrorCorrectionLevel::L())->getMatrix();
        $modules = max(1, $matrix->getWidth());

        $gap = 12;
        // QR ocupa ~46% da bobina; resto é texto.
        $qrTarget = (int) round(self::TARGET_WIDTH * 0.46);
        $modulePx = max(2, (int) floor(($qrTarget - 8) / ($modules + 4)));
        $quiet = 2 * $modulePx;
        $qrSize = ($modules * $modulePx) + (2 * $quiet);

        $font = self::resolveFontPath();
        $fontSize = 14;
        $lineHeight = 20;
        $paddingTop = 6;
        $paddingBottom = 4;

        $textWidth = max(180, self::TARGET_WIDTH - $qrSize - $gap);
        $textBlockHeight = $paddingTop + (count($infoLines) * $lineHeight) + $paddingBottom;
        $height = max($qrSize, $textBlockHeight);
        $width = $qrSize + $gap + $textWidth;

        $img = imagecreate($width, $height);
        if ($img === false) {
            throw new RuntimeException('Falha ao criar imagem do QR NFC-e.');
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        // Centraliza QR verticalmente se o texto for mais alto.
        $qrOffsetY = (int) max(0, intdiv($height - $qrSize, 2));

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }
                $px = $quiet + ($x * $modulePx);
                $py = $qrOffsetY + $quiet + ($y * $modulePx);
                imagefilledrectangle(
                    $img,
                    $px,
                    $py,
                    $px + $modulePx - 1,
                    $py + $modulePx - 1,
                    $black
                );
            }
        }

        $textX = $qrSize + $gap;
        // Centraliza o bloco de texto se sobrar altura.
        $textBlockInner = count($infoLines) * $lineHeight;
        $textY = (int) max($paddingTop, intdiv($height - $textBlockInner, 2));
        if ($font !== null) {
            $textY += $fontSize;
        }

        foreach ($infoLines as $line) {
            $line = self::sanitizeLine($line);
            if ($font !== null) {
                imagettftext($img, $fontSize, 0, $textX, $textY, $black, $font, $line);
                $textY += $lineHeight;
            } else {
                imagestring($img, 5, $textX, $textY - 14, $line, $black);
                $textY += $lineHeight;
            }
        }

        $escpos = new GdEscposImage;
        $escpos->readImageFromGdResource($img);
        imagedestroy($img);

        if ($escpos->getWidth() <= 0 || $escpos->getHeight() <= 0) {
            throw new RuntimeException('Imagem ESC/POS do QR ficou vazia.');
        }

        return $escpos;
    }

    /**
     * Quebra chave em linhas que cabem na coluna de texto.
     *
     * @return list<string>
     */
    public static function wrapChave(string $chave, int $charsPerLine = 20): array
    {
        $digits = preg_replace('/\D+/', '', $chave) ?? '';
        if ($digits === '') {
            return [];
        }

        $chunks = str_split($digits, max(8, $charsPerLine));

        return array_values($chunks);
    }

    private static function sanitizeLine(string $line): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);

        return $converted !== false ? $converted : $line;
    }

    private static function resolveFontPath(): ?string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\consolab.ttf',
            'C:\\Windows\\Fonts\\courbd.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\consola.ttf',
            'C:\\Windows\\Fonts\\cour.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
