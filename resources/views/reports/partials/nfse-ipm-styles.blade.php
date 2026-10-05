@php
    $fonteIpm = str_replace('\\', '/', resource_path('fonts/carlito'));
    // O dompdf grava o cache da fonte em storage/fonts e falha se a pasta não existir.
    if (! is_dir(storage_path('fonts'))) {
        @mkdir(storage_path('fonts'), 0775, true);
    }
@endphp
<style>
    @font-face {
        font-family: 'IpmCarlito';
        src: url('{{ $fonteIpm }}/Carlito-Regular.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    @font-face {
        font-family: 'IpmCarlito';
        src: url('{{ $fonteIpm }}/Carlito-Bold.ttf') format('truetype');
        font-weight: bold;
        font-style: normal;
    }

    @page {
        margin: 9mm 10mm;
    }

    body {
        margin: 0;
        padding: 0;
        color: #000;
        background: #fff;
        font-family: Calibri, 'IpmCarlito', Carlito, Arial, Helvetica, sans-serif;
        font-size: 7.5pt;
        line-height: 1.1;
    }

    .ipm {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .ipm td {
        border: 1px solid #000;
        vertical-align: top;
        padding: 1px 3px;
        word-wrap: break-word;
    }

    .ipm + .ipm {
        margin-top: -1px;
    }

    .ipm--interna td {
        border-width: 0 0 0 1px;
    }

    .ipm--interna td:first-child {
        border-left: 0;
    }

    .ipm__sem-pad {
        padding: 0 !important;
    }

    .ipm__prestador {
        padding: 3px 4px !important;
        line-height: 1.32;
    }

    .ipm__forte {
        font-weight: 700;
    }

    .ipm__centro {
        text-align: center;
    }

    .ipm__meio {
        vertical-align: middle !important;
    }

    .ipm__qr {
        text-align: center;
        vertical-align: middle !important;
    }

    .ipm__qr img {
        width: 19mm;
        height: 19mm;
        display: block;
        margin: 2px auto 3px;
    }

    .ipm__qr a {
        color: #1a0dab;
        text-decoration: underline;
    }

    .ipm__titulo {
        text-align: center;
        font-size: 12pt;
        font-weight: 700;
        padding: 1px 3px !important;
    }

    .ipm__pref {
        vertical-align: middle !important;
        font-size: 9pt;
        line-height: 1.3;
        padding: 0 3px !important;
    }

    .ipm__pref-tabela {
        border-collapse: collapse;
    }

    .ipm__pref-tabela td {
        border: 0;
        padding: 0;
        vertical-align: middle;
    }

    .ipm__brasao {
        width: 15mm;
        padding-left: 2mm !important;
    }

    .ipm__brasao img {
        width: 11mm;
        height: 14.6mm;
        display: block;
    }

    .ipm__ident {
        line-height: 1.2;
        padding: 2px 3px !important;
    }

    .ipm__barras {
        height: 9mm;
        margin: 1px 0 2px;
        text-align: center;
    }

    .ipm__barras img {
        width: 74mm;
        height: 9mm;
    }

    .ipm__sec {
        text-align: center;
        font-size: 8pt;
        padding: 2px 4px !important;
    }

    .ipm__cab {
        line-height: 1.15;
    }

    .ipm__par {
        margin: 0 0 7px;
    }

    .ipm__par:last-child {
        margin-bottom: 0;
    }

    .ipm__rodape {
        text-align: center;
        font-size: 7pt;
        margin-top: 2px;
    }

    .ipm__aviso {
        border: 1px solid #000;
        font-size: 8pt;
        font-weight: 700;
        text-align: center;
        padding: 2px 4px;
        margin: 0 0 3px;
    }

    .ipm__aviso--espelho,
    .ipm__aviso--homolog {
        color: #9f1239;
        border-color: #9f1239;
    }

    .ipm__marca {
        position: fixed;
        top: 42%;
        left: 0;
        width: 100%;
        text-align: center;
        font-size: 42pt;
        color: #8a8a8a;
        transform: rotate(-32deg);
        z-index: 5;
        letter-spacing: 1px;
    }

    .ipm__acoes {
        margin-top: 10px;
    }

    .ipm__acoes button {
        font: inherit;
        padding: 6px 12px;
    }

    .ipm-tela {
        padding: 14px 22px;
    }

    @media print {
        .ipm__acoes { display: none !important; }
        .ipm-tela { padding: 0; }
    }
</style>
