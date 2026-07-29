<style>
    .comissao-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .comissao-doc__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .comissao-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .comissao-doc__header {
        display: table;
        width: 100%;
    }

    .comissao-doc__logo-cell,
    .comissao-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .comissao-doc__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .comissao-doc__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .comissao-doc__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .comissao-doc__logo-fallback {
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

    .comissao-doc__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .comissao-doc__title {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        text-decoration: underline;
        margin: 2mm 0 3mm;
    }

    .comissao-doc__filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
        margin-bottom: 3mm;
        font-size: 10px;
        font-weight: 700;
    }

    .comissao-doc__table {
        width: 100%;
        border-collapse: collapse;
    }

    .comissao-doc__table th,
    .comissao-doc__table td {
        border: 1px solid #bbb;
        padding: 1.2mm 1.4mm;
        vertical-align: top;
    }

    .comissao-doc__table thead th {
        background: linear-gradient(180deg, #1e5a9e 0%, #0f3460 100%);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-align: left;
        border-color: rgba(255, 255, 255, 0.15);
    }

    .comissao-doc__table td.num,
    .comissao-doc__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .comissao-doc__table tfoot td {
        background: #eef4fb;
        font-weight: 700;
    }

    .comissao-doc__strong {
        font-weight: 800;
    }

    .comissao-doc__empty {
        text-align: center;
        padding: 4mm;
        color: #64748b;
        font-style: italic;
    }
</style>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/reports/partials/comissao-vendedores-document-styles.blade.php ENDPATH**/ ?>