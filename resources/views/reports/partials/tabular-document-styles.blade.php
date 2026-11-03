<style>
    .tabular-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .tabular-doc__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .tabular-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .tabular-doc__header {
        display: table;
        width: 100%;
    }

    .tabular-doc__logo-cell,
    .tabular-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .tabular-doc__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .tabular-doc__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .tabular-doc__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .tabular-doc__logo-fallback {
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

    .tabular-doc__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .tabular-doc__title {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        text-decoration: underline;
        margin: 2mm 0 3mm;
    }

    .tabular-doc__filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
        margin-bottom: 3mm;
        font-size: 10px;
        font-weight: 700;
    }

    .tabular-doc__table {
        width: 100%;
        border-collapse: collapse;
    }

    .tabular-doc__table th,
    .tabular-doc__table td {
        border: 1px solid #bbb;
        padding: 1.2mm 1.4mm;
        vertical-align: top;
        word-break: break-word;
    }

    .tabular-doc__table--compact th,
    .tabular-doc__table--compact td {
        padding: 1mm 1mm;
        font-size: 10px;
    }

    .tabular-doc__table--compact .col-nowrap {
        white-space: nowrap;
    }

    .tabular-doc__table thead th {
        background: #0f2847;
        color: #fff;
        font-weight: 700;
        text-align: left;
    }

    .tabular-doc__table tbody tr:nth-child(even) {
        background: #f1f5f9;
    }

    .tabular-doc__table .num {
        text-align: right;
        white-space: nowrap;
    }

    .tabular-doc__table tfoot td {
        font-weight: 700;
        background: #e2e8f0;
    }

    .tabular-doc__empty {
        text-align: center;
        padding: 8mm 2mm;
        color: #64748b;
    }

    .tabular-doc__strong {
        font-weight: 700;
    }

    .tabular-doc__meta {
        margin-top: 3mm;
        font-size: 9px;
        color: #475569;
        text-align: right;
    }

    .tabular-doc__qtd-toggle {
        display: inline;
        margin: 0;
        padding: 0;
        border: 0;
        background: none;
        color: #1d4ed8;
        font: inherit;
        font-weight: 700;
        text-decoration: underline;
        cursor: pointer;
    }

    .tabular-doc__qtd-toggle:hover,
    .tabular-doc__qtd-toggle:focus-visible {
        color: #1e40af;
    }

    .tabular-doc__qtd-toggle[aria-expanded="true"] {
        color: #1e3a8a;
    }

    .tabular-doc__detail-row > td {
        background: #f8fafc !important;
        padding: 2mm 2mm 2.5mm !important;
    }

    .tabular-doc__detail {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }

    .tabular-doc__detail th,
    .tabular-doc__detail td {
        border: 1px solid #cbd5e1;
        padding: 1mm 1.2mm;
        vertical-align: top;
    }

    .tabular-doc__detail thead th {
        background: #e2e8f0;
        color: #0f172a;
        font-weight: 700;
        text-align: left;
    }

    .tabular-doc__detail .num {
        text-align: right;
        white-space: nowrap;
    }

    .tabular-doc__detail-link {
        color: #1d4ed8;
        font-weight: 700;
        text-decoration: underline;
    }

    .tabular-doc__detail-link:hover {
        color: #1e40af;
    }

    .tabular-doc__detail-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem 0.75rem;
        margin-top: 1.5mm;
        font-size: 10px;
        color: #475569;
    }

    .tabular-doc__detail-more {
        min-width: 5.5rem;
        padding: 0.2rem 0.55rem;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
        color: #0f172a;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
    }

    .tabular-doc__detail-more:hover {
        border-color: #1e5a9e;
        background: #ffffff;
    }

    .tabular-doc__detail-status {
        color: #64748b;
        font-size: 10px;
    }

    @media print {
        .tabular-doc__qtd-toggle {
            color: inherit;
            font-weight: inherit;
            text-decoration: none;
            cursor: default;
        }

        .tabular-doc__detail-row {
            display: none !important;
        }
    }
</style>
