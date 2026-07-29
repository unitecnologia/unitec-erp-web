<style>
    .nfce-relatorio-doc {
        font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
        font-size: 8pt;
        color: #111;
        line-height: 1.25;
    }

    .nfce-relatorio-doc__frame {
        border: 0.6pt solid #222;
        padding: 5mm 5mm 11mm;
        background: #fff;
        min-height: 170mm;
        position: relative;
        margin-bottom: 0;
    }

    .nfce-relatorio-doc__frame--break {
        page-break-after: always;
    }

    .nfce-relatorio-doc__header-box,
    .nfce-relatorio-doc__title-box,
    .nfce-relatorio-doc__filter-box {
        border: 0.6pt solid #333;
    }

    .nfce-relatorio-doc__header-box {
        padding: 2mm 2.5mm;
        margin-bottom: 2mm;
    }

    .nfce-relatorio-doc__header {
        text-align: center;
    }

    .nfce-relatorio-doc__fantasia,
    .nfce-relatorio-doc__razao {
        display: block;
        font-weight: 700;
    }

    .nfce-relatorio-doc__fantasia {
        font-size: 10pt;
        margin-bottom: 0.6mm;
        letter-spacing: 0.2pt;
    }

    .nfce-relatorio-doc__razao {
        font-size: 8pt;
        margin-bottom: 0.6mm;
    }

    .nfce-relatorio-doc__address,
    .nfce-relatorio-doc__contact {
        display: block;
        font-size: 7.5pt;
        color: #222;
    }

    .nfce-relatorio-doc__title-box {
        margin-bottom: 2mm;
        padding: 1.6mm 2.5mm;
        background: #f3f4f6;
    }

    .nfce-relatorio-doc__title {
        text-align: center;
        font-size: 9pt;
        font-weight: 700;
        letter-spacing: 0.3pt;
        text-transform: uppercase;
    }

    .nfce-relatorio-doc__filter-box {
        margin-bottom: 3.5mm;
        padding: 1.4mm 2.5mm;
        font-size: 7.5pt;
        font-weight: 600;
        background: #fafafa;
    }

    .nfce-relatorio-doc__section-title {
        font-size: 7.5pt;
        font-weight: 700;
        margin: 3mm 0 0;
        padding: 1mm 2mm;
        border-top: 0.8pt solid #222;
        border-bottom: 0.8pt solid #222;
        background: #eef2f7;
    }

    .nfce-relatorio-doc__table-wrap {
        width: 100%;
    }

    .nfce-relatorio-doc__table-wrap--compact {
        width: min(100%, 110mm);
        margin: 0 auto;
    }

    .nfce-relatorio-doc__table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 7.2pt;
    }

    .nfce-relatorio-doc__table th,
    .nfce-relatorio-doc__table td {
        border: 0.5pt solid #444;
        padding: 1mm 1.4mm;
        vertical-align: middle;
        overflow: hidden;
    }

    .nfce-relatorio-doc__table th {
        background: #dbe3ee;
        font-weight: 700;
        text-align: center;
        font-size: 7pt;
        color: #111;
    }

    .nfce-relatorio-doc__table--resumido tbody td {
        border-top: none;
        border-bottom: none;
    }

    .nfce-relatorio-doc__table--resumido tbody tr:last-child td {
        border-bottom: 0.5pt solid #444;
    }

    .nfce-relatorio-doc__table .num {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .nfce-relatorio-doc__table .center {
        text-align: center;
        white-space: nowrap;
    }

    .nfce-relatorio-doc__table .chave {
        font-family: "DejaVu Sans Mono", "Courier New", monospace;
        font-size: 6.2pt;
        letter-spacing: 0;
        word-break: break-all;
        white-space: normal;
        line-height: 1.2;
        text-align: left;
    }

    .nfce-relatorio-doc__table .protocolo {
        font-family: "DejaVu Sans Mono", "Courier New", monospace;
        font-size: 6.4pt;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
    }

    .nfce-relatorio-doc__table--detalhado .col-numero {
        width: 8%;
    }

    .nfce-relatorio-doc__table--detalhado .col-emissao {
        width: 10%;
    }

    .nfce-relatorio-doc__table--detalhado .col-chave {
        width: 52%;
    }

    .nfce-relatorio-doc__table--detalhado .col-protocolo {
        width: 17%;
    }

    .nfce-relatorio-doc__table--detalhado .col-total {
        width: 13%;
    }

    .nfce-relatorio-doc__table--resumido .col-total,
    .nfce-relatorio-doc__table .col-total {
        width: 28mm;
    }

    .nfce-relatorio-doc__table .col-cst,
    .nfce-relatorio-doc__table .col-csosn {
        width: 18mm;
    }

    .nfce-relatorio-doc__subtotal td {
        border: none;
        background: transparent;
        padding-top: 1.2mm;
        padding-bottom: 1.6mm;
    }

    .nfce-relatorio-doc__total-bar {
        display: table;
        width: 100%;
        margin-top: 1.5mm;
        margin-bottom: 1.5mm;
        padding: 1mm 3mm;
        border: 0.8pt solid #222;
        border-radius: 3mm;
        background: #e8eef6;
        font-weight: 700;
        font-size: 8pt;
    }

    .nfce-relatorio-doc__total-label,
    .nfce-relatorio-doc__total-value {
        display: table-cell;
        vertical-align: middle;
    }

    .nfce-relatorio-doc__total-bar--grand {
        width: min(100%, 110mm);
        margin-left: auto;
        margin-right: auto;
        margin-top: 4mm;
    }

    .nfce-relatorio-doc__total-bar--section {
        width: 100%;
    }

    .nfce-relatorio-doc__total-label {
        text-align: right;
        padding-right: 3mm;
        width: 70%;
    }

    .nfce-relatorio-doc__total-value {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .nfce-relatorio-doc__footer {
        position: absolute;
        left: 6mm;
        right: 6mm;
        bottom: 4mm;
        display: table;
        width: calc(100% - 12mm);
        padding-top: 1.5mm;
        border-top: 0.5pt solid #666;
        font-size: 7pt;
        color: #333;
    }

    .nfce-relatorio-doc__footer span {
        display: table-cell;
    }

    .nfce-relatorio-doc__footer span:last-child {
        text-align: right;
    }

    .nfce-relatorio-doc__empty {
        padding: 8mm 0;
        text-align: center;
        font-style: italic;
        border: 0.5pt solid #666;
        background: #f7f7f7;
    }
</style>
