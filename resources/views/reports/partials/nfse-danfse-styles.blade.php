<style>
    @page {
        margin: 6mm;
        size: A4 portrait;
    }

    html, body {
        margin: 0;
        padding: 0;
        color: #000;
        background: #fff;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 7pt;
    }

    .danfse {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .danfse td,
    .danfse th {
        border: 1px solid #000;
        vertical-align: top;
        padding: 1.5px 3px;
    }

    .danfse__label {
        display: block;
        font-size: 5.5pt;
        font-weight: 400;
        line-height: 1.1;
        margin-bottom: 1px;
    }

    .danfse__value {
        display: block;
        font-size: 7pt;
        font-weight: 700;
        line-height: 1.15;
        word-break: break-word;
        white-space: pre-wrap;
    }

    .danfse__value--normal {
        font-weight: 400;
    }

    .danfse__value--center {
        text-align: center;
    }

    .danfse__section {
        background: #f2f2f2;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        padding: 2px 3px;
    }

    .danfse__section span {
        font-size: 5.5pt;
        font-weight: 400;
        text-transform: none;
        margin-left: 4px;
    }

    .danfse__brand {
        font-size: 16pt;
        font-weight: 700;
        line-height: 1;
    }

    .danfse__brand-sub {
        font-size: 6.5pt;
        margin-top: 2px;
    }

    .danfse__title {
        font-size: 14pt;
        font-weight: 700;
        text-align: center;
        line-height: 1.05;
    }

    .danfse__subtitle {
        font-size: 7pt;
        text-align: center;
        margin-top: 2px;
    }

    .danfse__aviso {
        border: 2px solid #000;
        font-size: 9pt;
        font-weight: 700;
        text-align: center;
        padding: 3px 4px;
        margin: 0 0 3px;
        letter-spacing: 0.4px;
    }

    .danfse__aviso--espelho {
        color: #b91c1c;
        border-color: #b91c1c;
        background: #fef2f2;
    }

    .danfse__aviso-sub {
        border: 1px solid #b91c1c;
        color: #991b1b;
        font-size: 7pt;
        font-weight: 600;
        text-align: center;
        padding: 2px 4px;
        margin: 0 0 4px;
        background: #fff7ed;
    }

    .danfse__chave {
        font-family: "Courier New", Courier, monospace;
        font-size: 8pt;
        font-weight: 700;
        letter-spacing: 0.5px;
        word-break: break-all;
    }

    .danfse__qr {
        width: 1.6cm;
        height: 1.6cm;
        display: block;
        margin: 0 auto 2px;
    }

    .danfse__qr-msg {
        font-size: 5pt;
        line-height: 1.15;
        text-align: center;
        font-weight: 400;
    }

    .danfse__intermediario {
        font-size: 7pt;
        font-weight: 700;
        text-align: center;
        padding: 3px;
        text-transform: uppercase;
    }

    .danfse__acoes {
        margin-top: 12px;
    }

    .danfse__acoes button {
        font: inherit;
        padding: 8px 14px;
    }

    @media print {
        .danfse__acoes { display: none !important; }
    }
</style>
