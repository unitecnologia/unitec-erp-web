<style>
    @page {
        margin: 7mm;
        size: A4 portrait;
    }

    html, body {
        margin: 0;
        padding: 0;
        color: #000;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 7.5pt;
    }

    .ipm {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .ipm td {
        border: 1px solid #000;
        vertical-align: top;
        padding: 2px 3px;
    }

    .ipm + .ipm {
        margin-top: -1px;
    }

    .ipm__lbl {
        display: block;
        font-size: 6pt;
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: 1px;
    }

    .ipm__val {
        display: block;
        font-size: 8pt;
        font-weight: 700;
        line-height: 1.2;
        word-wrap: break-word;
    }

    .ipm__txt {
        font-size: 7.5pt;
        font-weight: 400;
        line-height: 1.25;
    }

    .ipm__razao {
        font-size: 11pt;
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: 2px;
        text-transform: uppercase;
    }

    .ipm__linha {
        font-size: 7.5pt;
        line-height: 1.3;
    }

    .ipm__num {
        display: block;
        font-size: 14pt;
        font-weight: 700;
        text-align: center;
        line-height: 1.1;
        padding: 2px 0 1px;
    }

    .ipm__sit {
        display: block;
        font-size: 9pt;
        font-weight: 700;
        text-align: center;
        padding: 2px 0;
    }

    .ipm__centro {
        text-align: center;
    }

    .ipm__qr {
        text-align: center;
        vertical-align: middle;
    }

    .ipm__qr img {
        width: 22mm;
        height: 22mm;
        display: block;
        margin: 1px auto 0;
    }

    .ipm__pref {
        text-align: center;
        font-weight: 700;
        line-height: 1.35;
        padding: 4px 3px;
    }

    .ipm__pref-titulo {
        font-size: 8.5pt;
    }

    .ipm__pref-linha {
        font-size: 8pt;
        text-transform: uppercase;
    }

    .ipm__sec {
        background: #efefef;
        font-size: 8pt;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.2px;
        padding: 2px 4px;
        text-transform: uppercase;
    }

    .ipm__id {
        font-family: "Courier New", Courier, monospace;
        font-size: 8.5pt;
        font-weight: 700;
        letter-spacing: 0.4px;
        word-break: break-all;
    }

    .ipm__chave {
        font-family: "Courier New", Courier, monospace;
        font-size: 8pt;
        font-weight: 700;
        word-break: break-all;
    }

    .ipm__tot {
        text-align: center;
        padding: 1px 1px 2px;
    }

    .ipm__tot .ipm__lbl {
        font-size: 5pt;
        font-weight: 700;
        text-align: center;
    }

    .ipm__tot .ipm__val {
        font-size: 6.5pt;
        text-align: center;
    }

    .ipm__bloco {
        font-size: 7pt;
        line-height: 1.3;
        font-weight: 400;
    }

    .ipm__bloco strong {
        font-weight: 700;
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

    @media print {
        .ipm__acoes { display: none !important; }
    }
</style>
