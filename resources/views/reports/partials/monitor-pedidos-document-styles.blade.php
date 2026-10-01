<style>
    .monitor-pedidos-doc {
        font-family: Arial, Helvetica, sans-serif;
        color: #111827;
        font-size: 9.5pt;
        line-height: 1.25;
        box-sizing: border-box;
        max-width: 100%;
    }

    .monitor-pedidos-doc__pages {
        width: 100%;
        border-collapse: collapse;
    }

    /* Um <tr> = um pedido: pula para a próxima folha se não couber inteiro. */
    .monitor-pedidos-doc__pages > tbody > tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .monitor-pedidos-doc__pages > tbody > tr > td {
        padding: 0 0 0.1rem;
        vertical-align: top;
    }

    .monitor-pedidos-doc__pages > thead > tr > td {
        padding: 0 0 0.15rem;
    }

    .monitor-pedidos-doc__sheet-header {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #111827;
        margin: 0;
        font-weight: 700;
        font-size: 11pt;
    }

    .monitor-pedidos-doc__sheet-header td {
        text-align: center;
        padding: 0.28rem 0.55rem;
    }

    .monitor-pedidos-doc__block {
        margin: 0;
        /* Pedido pequeno: não fatiar no meio (cabeçalho numa folha, total na outra).
           Pedido maior que A4: o motor de impressão ainda pode quebrar o bloco. */
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .monitor-pedidos-doc__keep-start,
    .monitor-pedidos-doc__keep-end {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    /* Se o bloco grande precisar quebrar, evita total/reclamações órfãos. */
    .monitor-pedidos-doc__keep-start {
        page-break-after: avoid;
        break-after: avoid;
    }

    .monitor-pedidos-doc__keep-end {
        page-break-before: avoid;
        break-before: avoid;
    }

    .monitor-pedidos-doc__meta-line,
    .monitor-pedidos-doc__aviso,
    .monitor-pedidos-doc__rodape,
    .monitor-pedidos-doc__cut,
    .monitor-pedidos-doc__table tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    /* Une visualmente as tabelas fatiadas (abertura / itens / totais). */
    .monitor-pedidos-doc__table--continue {
        margin-top: -1px;
    }

    .monitor-pedidos-doc__cut {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin: 0.25rem 0 0.3rem;
        color: #4b5563;
        font-size: 8pt;
        line-height: 1;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .monitor-pedidos-doc__cut-icon {
        flex: 0 0 auto;
        font-size: 11pt;
        line-height: 1;
        transform: rotate(-90deg);
    }

    .monitor-pedidos-doc__cut-label {
        flex: 0 0 auto;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        font-size: 7.5pt;
        color: #6b7280;
    }

    .monitor-pedidos-doc__cut-line {
        flex: 1 1 auto;
        height: 0;
        border-top: 1px dashed #6b7280;
    }

    .monitor-pedidos-doc__meta {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #111827;
        margin: 0;
        font-size: 8.5pt;
        table-layout: fixed;
    }

    .monitor-pedidos-doc__meta-wrap {
        position: relative;
        margin: 0 0 0.2rem;
    }

    .monitor-pedidos-doc__status {
        position: absolute;
        top: 0.2rem;
        right: 0.28rem;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 4.6rem;
        padding: 0.12rem 0.45rem;
        border-radius: 3px;
        font-size: 8pt;
        font-weight: 800;
        letter-spacing: 0.02em;
        line-height: 1.15;
        color: #ffffff;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .monitor-pedidos-doc__status--pendente {
        background: #0f172a;
    }

    .monitor-pedidos-doc__status--financeiro {
        background: #eab308;
        color: #111827;
    }

    .monitor-pedidos-doc__status--confirmado {
        background: #2563eb;
    }

    .monitor-pedidos-doc__status--faturado {
        background: #16a34a;
    }

    .monitor-pedidos-doc__status--cancelado {
        background: #dc2626;
    }

    .monitor-pedidos-doc__meta td {
        vertical-align: top;
        padding: 0.28rem 0.4rem;
        width: 50%;
    }

    .monitor-pedidos-doc__meta td:last-child {
        padding-right: 5.4rem;
    }

    .monitor-pedidos-doc__meta-line {
        display: block;
        margin: 0 0 0.06rem;
        word-break: break-word;
    }

    .monitor-pedidos-doc__meta-label {
        font-weight: 700;
    }

    .monitor-pedidos-doc__pedido-num {
        font-weight: 800;
    }

    .monitor-pedidos-doc__table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 8.5pt;
    }

    .monitor-pedidos-doc__table th,
    .monitor-pedidos-doc__table td {
        border: 1px solid #111827;
        padding: 1px 0.28rem;
        line-height: 1.1;
        vertical-align: top;
        box-sizing: border-box;
    }

    .monitor-pedidos-doc__table-head th {
        background: #d1d5db;
        font-weight: 700;
        text-align: center;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .monitor-pedidos-doc__table .col-codigo { width: 10%; text-align: center; }
    .monitor-pedidos-doc__table .col-produto { width: 44%; text-align: left; }
    .monitor-pedidos-doc__table .col-un { width: 7%; text-align: center; }
    .monitor-pedidos-doc__table .col-qtd { width: 11%; text-align: right; }
    .monitor-pedidos-doc__table .col-unit { width: 13%; text-align: right; }
    .monitor-pedidos-doc__table .col-desc { width: 11%; text-align: right; }
    .monitor-pedidos-doc__table .col-sub {
        width: 15%;
        text-align: right;
        padding-right: 0.45rem;
    }

    .monitor-pedidos-doc__table--com-desconto .col-produto { width: 36%; }
    .monitor-pedidos-doc__table--com-desconto .col-unit { width: 11%; }
    .monitor-pedidos-doc__table--com-desconto .col-sub { width: 14%; }

    .monitor-pedidos-doc__table tbody td.produto {
        text-align: left;
        word-break: break-word;
    }

    .monitor-pedidos-doc__totals-row td {
        background: #e5e7eb;
        font-weight: 700;
        padding-top: 0.12rem;
        padding-bottom: 0.12rem;
        line-height: 1.25;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .monitor-pedidos-doc__totals-row .totais-label {
        text-align: center;
    }

    .monitor-pedidos-doc__aviso {
        margin: 0.12rem 0 0;
        font-size: 7.5pt;
        font-weight: 700;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.01em;
    }

    .monitor-pedidos-doc__rodape {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.75rem;
        margin: 0.18rem 0 0;
    }

    .monitor-pedidos-doc__impresso {
        font-size: 7pt;
        font-weight: 400;
        color: #4b5563;
        text-align: left;
        letter-spacing: 0.01em;
    }

    .monitor-pedidos-doc__credito {
        margin: 0;
        font-size: 6.5pt;
        font-weight: 400;
        color: #9ca3af;
        text-align: right;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .monitor-pedidos-doc__footer-page {
        margin: 0.1rem 0 0;
        padding: 0;
        font-size: 7pt;
        font-weight: 400;
        color: #4b5563;
        text-align: right;
        white-space: nowrap;
    }

    .monitor-pedidos-doc__page-layer {
        display: none;
    }

    .monitor-pedidos-doc__print-footer {
        display: none;
    }

    @page {
        size: A4 portrait;
        margin: 10mm 13mm 14mm 10mm;
    }

    @media print {
        .monitor-pedidos-doc__print-footer {
            display: none;
        }

        .monitor-pedidos-doc__footer-page {
            visibility: hidden;
        }

        .monitor-pedidos-doc__page-layer {
            display: block;
            position: absolute;
            right: 0;
            left: auto;
            width: auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            font-weight: 400;
            color: #4b5563;
            z-index: 5;
        }

        .viewer__paper,
        .monitor-pedidos-doc {
            width: 100% !important;
            max-width: 100% !important;
        }

        .monitor-pedidos-doc__pages,
        .monitor-pedidos-doc__meta,
        .monitor-pedidos-doc__table,
        .monitor-pedidos-doc__sheet-header {
            width: 100% !important;
            max-width: 100% !important;
        }

        .monitor-pedidos-doc__table .col-sub {
            padding-right: 0.55rem !important;
        }

        .monitor-pedidos-doc__status {
            right: 0.4rem;
        }
    }
</style>
