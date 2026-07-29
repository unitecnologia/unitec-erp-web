<?php

$srcPath = dirname(__DIR__) . '/public/img/erp/brand/unitecnologia-logo.png';

// Restaura original
$original = dirname(__DIR__) . '/storage/app/public/empresa-logos/DQrdNb37OclMWNFsviGE4PPkH7RRj1wltMNagkZs.png';
if (! is_file($original)) {
    fwrite(STDERR, "original missing\n");
    exit(1);
}

copy($original, $srcPath);

$src = imagecreatefrompng($srcPath);
if (! $src) {
    fwrite(STDERR, "fail load\n");
    exit(1);
}

$w = imagesx($src);
$h = imagesy($src);

imagealphablending($src, false);
imagesavealpha($src, true);

$dst = imagecreatetruecolor($w, $h);
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);

$isBg = static function (int $r, int $g, int $b): bool {
    $avg = ($r + $g + $b) / 3;
    $sat = max($r, $g, $b) - min($r, $g, $b);

    // branco / cinza-claro (halo de anti-alias)
    if ($avg >= 188 && $sat <= 36) {
        return true;
    }

    // franja bem clara
    if ($avg >= 215 && $sat <= 55) {
        return true;
    }

    return false;
};

// Flood fill a partir das bordas
$visited = array_fill(0, $w * $h, false);
$queue = [];

$push = static function (int $x, int $y) use (&$queue, &$visited, $w, $h): void {
    if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
        return;
    }
    $i = $y * $w + $x;
    if ($visited[$i]) {
        return;
    }
    $visited[$i] = true;
    $queue[] = [$x, $y];
};

for ($x = 0; $x < $w; $x++) {
    $push($x, 0);
    $push($x, $h - 1);
}
for ($y = 0; $y < $h; $y++) {
    $push(0, $y);
    $push($w - 1, $y);
}

$bgMask = array_fill(0, $w * $h, false);

while ($queue !== []) {
    [$x, $y] = array_pop($queue);
    $rgb = imagecolorat($src, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    if (! $isBg($r, $g, $b)) {
        continue;
    }

    $bgMask[$y * $w + $x] = true;
    $push($x + 1, $y);
    $push($x - 1, $y);
    $push($x, $y + 1);
    $push($x, $y - 1);
}

// Cópia final + suaviza borda residual (1px de limpeza)
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $i = $y * $w + $x;
        $rgb = imagecolorat($src, $x, $y);
        $a = ($rgb & 0x7F000000) >> 24;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        if ($bgMask[$i] || $isBg($r, $g, $b)) {
            imagesetpixel($dst, $x, $y, $transparent);
            continue;
        }

        // Se vizinho é fundo e o pixel é meio-claro pouco saturado, remove halo
        $avg = ($r + $g + $b) / 3;
        $sat = max($r, $g, $b) - min($r, $g, $b);
        $nearBg = false;
        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                continue;
            }
            if ($bgMask[$ny * $w + $nx]) {
                $nearBg = true;
                break;
            }
        }

        if ($nearBg && $avg >= 160 && $sat <= 40) {
            imagesetpixel($dst, $x, $y, $transparent);
            continue;
        }

        if ($nearBg && $avg >= 140 && $sat <= 25) {
            $col = imagecolorallocatealpha($dst, $r, $g, $b, 100);
            imagesetpixel($dst, $x, $y, $col);
            continue;
        }

        $col = imagecolorallocatealpha($dst, $r, $g, $b, $a);
        imagesetpixel($dst, $x, $y, $col);
    }
}

imagepng($dst, $srcPath, 6);
imagedestroy($src);
imagedestroy($dst);

echo "ok {$w}x{$h}\n";
