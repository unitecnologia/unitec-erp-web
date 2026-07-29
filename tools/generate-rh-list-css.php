<?php

$src = dirname(__DIR__).'/public/css/erp-logistica-simple.css';
$dst = dirname(__DIR__).'/public/css/erp-rh-list.css';

$css = file_get_contents($src);
$css = str_replace(
    [
        '/* ERP — Logística simples (tomador, destinatário, remetente) */',
        'erp-tomadores-servico',
        'erp-logistica-destinatarios',
        'erp-logistica-remetentes',
    ],
    [
        '/* ERP — Mini RH listagens (funcionários, cargos, departamentos) — gerado a partir de erp-logistica-simple.css */',
        'erp-rh-funcionarios',
        'erp-rh-cargos',
        'erp-rh-departamentos',
    ],
    $css
);

file_put_contents($dst, $css);
echo "Wrote {$dst} (".strlen($css)." bytes)\n";
