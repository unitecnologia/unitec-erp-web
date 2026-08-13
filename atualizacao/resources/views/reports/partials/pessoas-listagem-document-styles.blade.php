<style>
    .pessoa-list-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .pessoa-list-doc__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .pessoa-list-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .pessoa-list-doc__header {
        display: table;
        width: 100%;
    }

    .pessoa-list-doc__logo-cell,
    .pessoa-list-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .pessoa-list-doc__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .pessoa-list-doc__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .pessoa-list-doc__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .pessoa-list-doc__logo-fallback {
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

    .pessoa-list-doc__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .pessoa-list-doc__title {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        text-decoration: underline;
        margin: 2mm 0 3mm;
    }

    .pessoa-list-doc__filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
        margin-bottom: 3mm;
        font-size: 10px;
        font-weight: 700;
    }

    .pessoa-list-doc__table {
        width: 100%;
        border-collapse: collapse;
    }

    .pessoa-list-doc__table th,
    .pessoa-list-doc__table td {
        border: 1px solid #bbb;
        padding: 1.2mm 1.4mm;
        vertical-align: top;
    }

    .pessoa-list-doc__table thead th {
        background: linear-gradient(180deg, #1e5a9e 0%, #0f3460 100%);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-align: left;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .pessoa-list-doc__table td.num,
    .pessoa-list-doc__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .pessoa-list-doc__table td.nome {
        word-break: break-word;
    }

    .pessoa-list-doc__empty {
        text-align: center;
        font-style: italic;
        color: #666;
    }

    .pessoa-list-doc__table tfoot td {
        background: #d9d9d9;
        font-weight: 700;
        border-top: 2px solid #888;
    }

    .pessoa-list-doc__footer {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 3mm;
        padding-top: 2mm;
        border-top: 1px solid #111;
        font-size: 10px;
    }

    .pessoa-list-doc__footer-page {
        white-space: nowrap;
    }
</style>
