<style>
    .cr-cartoes-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #111;
        line-height: 1.3;
    }

    .cr-cartoes-doc__frame {
        border: 1px solid #111;
        padding: 6mm 5mm;
        background: #fff;
    }

    .cr-cartoes-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 2.5mm 0;
    }

    .cr-cartoes-doc__header {
        display: table;
        width: 100%;
    }

    .cr-cartoes-doc__logo-cell,
    .cr-cartoes-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .cr-cartoes-doc__logo-cell {
        width: 20mm;
        padding-right: 4mm;
    }

    .cr-cartoes-doc__logo {
        width: 18mm;
        height: 18mm;
        border: 1px solid #bbb;
        text-align: center;
    }

    .cr-cartoes-doc__logo img {
        max-width: 16mm;
        max-height: 16mm;
    }

    .cr-cartoes-doc__logo-fallback {
        display: inline-block;
        margin-top: 4mm;
        font-size: 16px;
        font-weight: 800;
    }

    .cr-cartoes-doc__company-name {
        display: block;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 1mm;
    }

    .cr-cartoes-doc__title {
        text-align: center;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0.04em;
        margin: 2mm 0;
    }

    .cr-cartoes-doc__filters {
        display: flex;
        flex-wrap: wrap;
        gap: 2mm 4mm;
        margin-bottom: 3mm;
        font-size: 9px;
        font-weight: 700;
    }

    .cr-cartoes-doc__table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .cr-cartoes-doc__table th,
    .cr-cartoes-doc__table td {
        border: 1px solid #333;
        padding: 1.2mm 1.4mm;
        vertical-align: top;
        word-wrap: break-word;
    }

    .cr-cartoes-doc__table th {
        background: #e8eef6;
        font-size: 8.5px;
        text-align: left;
    }

    .cr-cartoes-doc__table td.num,
    .cr-cartoes-doc__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .cr-cartoes-doc__empty {
        text-align: center;
        padding: 6mm !important;
        color: #555;
    }

    .cr-cartoes-doc__totals td {
        font-weight: 800;
        background: #f3f6fb;
    }

    .cr-cartoes-doc__bandeiras {
        margin-top: 4mm;
        width: 55%;
        border-collapse: collapse;
    }

    .cr-cartoes-doc__bandeiras th,
    .cr-cartoes-doc__bandeiras td {
        border: 1px solid #333;
        padding: 1.2mm 1.5mm;
    }

    .cr-cartoes-doc__bandeiras th {
        background: #e8eef6;
        text-align: left;
        font-size: 8.5px;
    }

    .cr-cartoes-doc__footer {
        display: flex;
        justify-content: space-between;
        margin-top: 3mm;
        font-size: 8.5px;
    }
</style>
