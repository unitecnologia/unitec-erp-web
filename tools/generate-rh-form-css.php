<?php

$src = dirname(__DIR__).'/public/css/erp-veiculos.css';
$dst = dirname(__DIR__).'/public/css/erp-rh-form.css';
$css = file_get_contents($src);
$pos = strpos($css, '/* ── Modal cadastro');
if ($pos === false) {
    fwrite(STDERR, "Marker not found\n");
    exit(1);
}
$chunk = substr($css, $pos);
$chunk = str_replace('.erp-veiculos-page', '.erp-rh-funcionarios-page', $chunk);
$chunk = str_replace(
    '/* ── Modal cadastro (F2 / F3) ─────────────────────────────────── */',
    '/* RH — modal cadastro funcionário (mesmo padrão do Veículos) */',
    $chunk
);
file_put_contents($dst, $chunk);
echo 'Wrote '.$dst.' ('.strlen($chunk)." bytes)\n";
