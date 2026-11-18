<?php

/**
 * Gera public/css/erp-rh-list.css a partir de erp-logistica-simple.css.
 * Inclui funcionários, cargos, departamentos, documentos, EPIs e exames.
 */
$src = dirname(__DIR__).'/public/css/erp-logistica-simple.css';
$dst = dirname(__DIR__).'/public/css/erp-rh-list.css';

$css = file_get_contents($src);
if ($css === false) {
    fwrite(STDERR, "Não leu {$src}\n");
    exit(1);
}

$css = str_replace(
    [
        '/* ERP — Logística simples (tomador, destinatário, remetente) */',
        'erp-tomadores-servico',
        'erp-logistica-destinatarios',
        'erp-logistica-remetentes',
    ],
    [
        '/* ERP — Mini RH listagens (funcionários, cargos, departamentos, documentos, EPIs, exames) — gerado a partir de erp-logistica-simple.css */',
        'erp-rh-funcionarios',
        'erp-rh-cargos',
        'erp-rh-departamentos',
    ],
    $css
);

$extraPages = [
    'erp-rh-documentos',
    'erp-rh-epis',
    'erp-rh-uniformes',
    'erp-rh-exames',
    'erp-rh-treinamentos',
    'erp-rh-holerites',
    'erp-rh-beneficios',
    'erp-rh-ocorrencias',
    'erp-rh-ferias',
    'erp-rh-escalas',
];
$lines = explode("\n", $css);
$out = [];

foreach ($lines as $line) {
    if (! str_contains($line, 'erp-rh-departamentos')) {
        $out[] = $line;

        continue;
    }

    $trimmed = rtrim($line);
    $mirrors = [];
    foreach ($extraPages as $page) {
        $mirror = str_replace('erp-rh-departamentos', $page, $trimmed);
        if ($mirror !== $trimmed) {
            $mirrors[] = $mirror;
        }
    }

    if ($mirrors === []) {
        $out[] = $line;

        continue;
    }

    if (str_ends_with($trimmed, '{')) {
        $before = rtrim(substr($trimmed, 0, -1));
        $out[] = $before.',';
        foreach ($mirrors as $i => $mirror) {
            $mirrorBefore = rtrim(substr(rtrim($mirror), 0, -1));
            $isLast = $i === count($mirrors) - 1;
            $out[] = $isLast ? $mirrorBefore.' {' : $mirrorBefore.',';
        }

        continue;
    }

    if (str_ends_with($trimmed, ',')) {
        $out[] = $trimmed;
        foreach ($mirrors as $mirror) {
            $out[] = str_ends_with($mirror, ',') ? $mirror : $mirror.',';
        }

        continue;
    }

    $out[] = $line;
    foreach ($mirrors as $mirror) {
        $out[] = $mirror;
    }
}

$content = implode("\n", $out);
file_put_contents($dst, $content);
echo "Wrote {$dst} (".strlen($content)." bytes)\n";
