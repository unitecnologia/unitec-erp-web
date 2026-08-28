<style>
    .recibo-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11pt;
        color: #111;
        line-height: 1.35;
    }

    .recibo-doc__frame {
        border: 1px solid #cbd5e1;
        padding: 8mm 7mm;
        background: #fff;
    }

    .recibo-doc__header {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .recibo-doc__brand,
    .recibo-doc__title-block {
        display: table-cell;
        vertical-align: top;
    }

    .recibo-doc__brand {
        width: 68%;
        padding-right: 4mm;
    }

    .recibo-doc__title-block {
        width: 32%;
        text-align: right;
    }

    .recibo-doc__logo {
        display: block;
        max-width: 14mm;
        max-height: 14mm;
        margin-bottom: 2mm;
    }

    .recibo-doc__empresa {
        font-size: 12pt;
        font-weight: 700;
        margin-bottom: 1mm;
        text-transform: uppercase;
    }

    .recibo-doc__meta {
        font-size: 8pt;
        margin: 0;
    }

    .recibo-doc__title {
        font-size: 16pt;
        font-weight: 700;
        color: #1e5a9e;
        line-height: 1.1;
    }

    .recibo-doc__codigo {
        font-size: 11pt;
        font-weight: 700;
        margin-top: 1mm;
    }

    .recibo-doc__rule {
        height: 0;
        border: none;
        border-top: 2px solid #1e5a9e;
        margin: 3mm 0;
    }

    .recibo-doc__valor-box {
        display: table;
        width: 100%;
        table-layout: fixed;
        border: 1px solid #94a3b8;
        background: #f1f5f9;
        margin-bottom: 4mm;
    }

    .recibo-doc__valor-label,
    .recibo-doc__valor {
        display: table-cell;
        vertical-align: middle;
        padding: 2.5mm 3mm;
    }

    .recibo-doc__valor-label {
        width: 30%;
        font-size: 9pt;
        font-weight: 700;
    }

    .recibo-doc__valor {
        width: 70%;
        text-align: right;
        font-size: 14pt;
        font-weight: 700;
        white-space: nowrap;
    }

    .recibo-doc__texto {
        font-size: 11pt;
        line-height: 1.4;
        margin: 0 0 4mm;
        text-align: justify;
        word-wrap: break-word;
        overflow-wrap: anywhere;
    }

    .recibo-doc__texto strong {
        font-weight: 700;
        text-transform: uppercase;
    }

    .recibo-doc__data {
        font-size: 10pt;
        margin: 0 0 6mm;
        text-transform: uppercase;
    }

    .recibo-doc__assinatura {
        text-align: center;
        font-size: 9pt;
        margin: 8mm 0 0;
    }

    .recibo-doc__linha {
        width: 55mm;
        margin: 0 auto 1.5mm;
        border-top: 1px solid #111;
    }
</style>
