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

    /* Faixa de via: só na impressão (clonada em beforeprint). */
    .monitor-pedidos-doc__via-banner {
        display: none;
        width: 100%;
        box-sizing: border-box;
        margin: 0 0 0.35rem;
        padding: 0.28rem 0.55rem;
        border: 1px solid #111827;
        text-align: center;
        font-weight: 800;
        font-size: 11pt;
        letter-spacing: 0.04em;
        color: #111827;
        background: #f3f4f6;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Segunda via segue na mesma folha (como o recibo). Sem page-break obrigatório. */
    .monitor-pedidos-doc--via-cliente {
        page-break-before: auto;
        break-before: auto;
        break-after: auto;
        page-break-after: auto;
    }

    .monitor-pedidos-doc__via-sep {
        margin: 2.2mm 0 2.6mm;
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-before: auto;
        break-before: auto;
        page-break-after: avoid;
        break-after: avoid;
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

    .monitor-pedidos-doc__empresa-cab {
        margin: 0 0 0.22rem;
        padding: 0.18rem 0.35rem;
        border: 1px solid #111827;
        font-size: 8.5pt;
        line-height: 1.2;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .monitor-pedidos-doc__empresa-cab-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .monitor-pedidos-doc__empresa-cab-table td {
        vertical-align: middle;
        padding: 0;
        border: 0;
    }

    .monitor-pedidos-doc__empresa-cab-logo {
        width: 22mm;
        padding-right: 2.5mm;
    }

    .monitor-pedidos-doc__empresa-cab-logo img {
        display: block;
        max-width: 20mm;
        max-height: 14mm;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .monitor-pedidos-doc__empresa-cab-dados {
        width: auto;
    }

    .monitor-pedidos-doc__empresa-cab-nome {
        font-weight: 800;
        font-size: 9.5pt;
        margin: 0 0 0.04rem;
        word-break: break-word;
    }

    .monitor-pedidos-doc__empresa-cab-line {
        display: block;
        margin: 0;
        word-break: break-word;
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

    /* Linhas em branco da meia folha: mesma grade, sem conteúdo. */
    .monitor-pedidos-doc__blank-row td {
        color: transparent;
        user-select: none;
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
        text-align: center;
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
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
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

        .monitor-pedidos-doc__via-banner {
            display: block;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: avoid;
            break-after: avoid;
        }

        body.erp-print-duas-vias .viewer,
        body.erp-print-meia-folha .viewer {
            min-height: 0;
            height: auto;
            display: block;
        }

        body.erp-print-duas-vias .viewer__canvas,
        body.erp-print-meia-folha .viewer__canvas {
            flex: none;
            display: block;
            overflow: visible;
        }

        /* Só no fluxo de 2 vias: o pedido pode continuar na folha seguinte
           entre linhas. Cabeçalho, linha, totais e corte continuam inteiros. */
        .viewer__paper--duas-vias:not(.viewer__paper--meia-folha-duas) .monitor-pedidos-doc__pages > tbody > tr,
        .viewer__paper--duas-vias:not(.viewer__paper--meia-folha-duas) .monitor-pedidos-doc__block {
            page-break-inside: auto;
            break-inside: auto;
        }

        .viewer__paper--duas-vias .monitor-pedidos-doc--via-cliente {
            page-break-before: auto;
            break-before: auto;
            page-break-after: auto;
            break-after: auto;
        }

        .viewer__paper--duas-vias .monitor-pedidos-doc__via-banner + .monitor-pedidos-doc__pages {
            page-break-before: avoid;
            break-before: avoid;
        }

        /* Meia folha A4: slot padronizado (sem cortar conteúdo — só aplica se couber). */
        .viewer__paper--meia-folha .monitor-pedidos-doc--meia-folha-slot {
            box-sizing: border-box;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* 2 vias + meia folha: uma via em cada metade, com faixa de corte no meio. */
        .viewer__paper--meia-folha-duas .monitor-pedidos-doc--meia-folha-slot {
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: avoid;
            break-after: avoid;
        }

        .viewer__paper--meia-folha-duas .monitor-pedidos-doc--via-cliente {
            page-break-before: avoid;
            break-before: avoid;
        }

        .viewer__paper--meia-folha-duas .monitor-pedidos-doc__via-sep--meia-folha {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin: 2.4mm 0 2.6mm;
            padding: 0.6mm 0;
            height: auto;
            overflow: visible;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-before: avoid;
            break-before: avoid;
            page-break-after: avoid;
            break-after: avoid;
        }
    }
</style>
