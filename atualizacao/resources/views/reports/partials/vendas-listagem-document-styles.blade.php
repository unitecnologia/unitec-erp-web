<style>
    .venda-list-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .venda-list-doc__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .venda-list-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .venda-list-doc__header {
        display: table;
        width: 100%;
    }

    .venda-list-doc__logo-cell,
    .venda-list-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .venda-list-doc__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .venda-list-doc__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .venda-list-doc__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .venda-list-doc__logo-fallback {
        display: inline-block;
        width: 16mm;
        height: 16mm;
        line-height: 16mm;
        border-radius: 50%;
        background: #16a34a;
        color: #fff;
        font-size: 14px;
        font-weight: 800;
        text-align: center;
    }

    .venda-list-doc__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .venda-list-doc__title {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        text-decoration: underline;
        margin: 2mm 0 3mm;
    }

    .venda-list-doc__filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
        margin-bottom: 3mm;
        font-size: 10px;
        font-weight: 700;
    }

    .venda-list-doc__table {
        width: 100%;
        border-collapse: collapse;
    }

    .venda-list-doc__table th,
    .venda-list-doc__table td {
        border: 1px solid #bbb;
        padding: 1.2mm 1.4mm;
        vertical-align: top;
    }

    .venda-list-doc__table thead th {
        background: linear-gradient(180deg, #1e5a9e 0%, #0f3460 100%);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-align: left;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .venda-list-doc__table td.num,
    .venda-list-doc__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .venda-list-doc__table td.texto {
        word-break: break-word;
    }

    .venda-list-doc__empty {
        text-align: center;
        font-style: italic;
        color: #666;
    }

    .venda-list-doc__table tfoot td {
        background: #d9d9d9;
        font-weight: 700;
        border-top: 2px solid #888;
    }

    .venda-list-doc__footer {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 3mm;
        padding-top: 2mm;
        border-top: 1px solid #111;
        font-size: 10px;
    }

    .venda-list-doc__footer-page {
        white-space: nowrap;
    }
</style>
