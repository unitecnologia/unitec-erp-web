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
        padding: 0.65mm 0.75mm;
        font-size: 9.5px;
        line-height: 1.25;
    }

    .tabular-doc__table--compact .col-nowrap,
    .tabular-doc__table .col-nowrap {
        white-space: nowrap;
        word-break: normal;
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

    .tabular-doc__section {
        margin: 1.6mm 0 1.2mm;
    }

    .tabular-doc__section-title {
        font-size: 10.5px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.8mm;
    }

    .tabular-doc__section-note {
        font-size: 8.5px;
        color: #64748b;
        margin: 0 0 0.8mm;
        font-weight: 600;
    }

    .tabular-doc__table--summary {
        width: 100%;
        max-width: 100%;
    }

    .tabular-doc__table--summary th,
    .tabular-doc__table--summary td {
        padding: 0.7mm 1mm;
        font-size: 9.5px;
        border-color: #9ca3af;
    }

    .tabular-doc__table--summary thead th {
        background: #e5e7eb;
        color: #111827;
        font-weight: 700;
    }

    .tabular-doc__table--summary tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    .tabular-doc__table--summary tfoot td {
        background: #eef2f7;
    }

    .tabular-doc__table--summary-plain td {
        border-color: #9ca3af;
    }

    .tabular-doc__chart-page {
        margin-top: 4mm;
        page-break-before: always;
        break-before: page;
    }

    .tabular-doc__chart-page--only {
        margin-top: 1mm;
        page-break-before: auto;
        break-before: auto;
    }

    .tabular-doc__chart-bars {
        display: flex;
        align-items: flex-end;
        gap: 1.2mm;
        height: 55mm;
        margin: 2mm 0 3mm;
        padding: 2mm 1mm 0;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        overflow: hidden;
    }

    .tabular-doc__chart-bar {
        flex: 1 1 0;
        min-width: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        gap: 0.6mm;
    }

    .tabular-doc__chart-bar-value {
        font-size: 6.5px;
        font-weight: 700;
        color: #334155;
        white-space: nowrap;
        transform: rotate(-65deg);
        transform-origin: center bottom;
        margin-bottom: 1mm;
        max-width: 18mm;
        overflow: hidden;
    }

    .tabular-doc__chart-bar-fill {
        width: 70%;
        max-width: 8mm;
        min-height: 1mm;
        border-radius: 2px 2px 0 0;
        background: linear-gradient(180deg, #3b82f6 0%, #1e5a9e 100%);
    }

    .tabular-doc__chart-bar-label {
        font-size: 7px;
        font-weight: 700;
        color: #475569;
        white-space: nowrap;
        line-height: 1;
        padding-bottom: 0.5mm;
    }

    .tabular-doc__chart-bar-label.is-hidden {
        visibility: hidden;
    }

    .tabular-doc__chart-table {
        max-width: 90mm;
    }

    .tabular-doc__hourly-chart {
        margin-top: 3mm;
        page-break-inside: avoid;
    }

    .tabular-doc__hourly-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        max-width: 100%;
        padding: 7mm 0 1mm;
    }

    .tabular-doc__chart-bars--hourly {
        position: relative;
        height: 34mm;
        min-width: calc(var(--hourly-cols, 8) * 11mm);
        gap: 1.5mm;
        padding: 1.2mm 1.5mm 0.8mm;
        align-items: stretch;
        overflow: visible;
    }

    .tabular-doc__hourly-grid {
        position: absolute;
        inset: 5.5mm 1.5mm 5mm 1.5mm;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        pointer-events: none;
        z-index: 0;
    }

    .tabular-doc__hourly-grid > span {
        display: block;
        width: 100%;
        border-top: 1px solid #e2e8f0;
    }

    .tabular-doc__chart-bar--hourly {
        position: relative;
        z-index: 1;
        flex: 1 0 9mm;
        min-width: 9mm;
        max-width: 14mm;
        gap: 0.8mm;
        justify-content: flex-end;
        cursor: default;
    }

    .tabular-doc__chart-bar-value--hourly {
        transform: none;
        font-size: 8.5px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
        margin: 0;
        max-width: none;
        overflow: visible;
        text-align: center;
        white-space: nowrap;
    }

    .tabular-doc__chart-bar-track {
        flex: 1 1 auto;
        width: 100%;
        min-height: 12mm;
        display: flex;
        align-items: flex-end;
        justify-content: center;
    }

    .tabular-doc__chart-bar--hourly .tabular-doc__chart-bar-fill {
        width: 62%;
        max-width: 7mm;
        min-height: 1.2mm;
    }

    .tabular-doc__chart-bar-label--hourly {
        font-size: 8.5px;
        font-weight: 700;
        color: #1e293b;
        padding-bottom: 0;
    }

    .tabular-doc__chart-bar--hourly[data-tip]:hover::after,
    .tabular-doc__chart-bar--hourly[data-tip]:focus-visible::after {
        content: attr(data-tip);
        position: absolute;
        left: 50%;
        top: -5.5mm;
        transform: translateX(-50%);
        z-index: 20;
        min-width: 22mm;
        max-width: 48mm;
        padding: 1.4mm 2.2mm;
        border-radius: 5px;
        border: 1px solid #94a3b8;
        background: #ffffff;
        color: #0f172a;
        font-size: 9px;
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
        pointer-events: none;
    }

    .tabular-doc__chart-bar-fill--qtd {
        background: linear-gradient(180deg, #60a5fa 0%, #1e5a9e 100%);
    }

    .tabular-doc__chart-bar-fill--valor {
        background: linear-gradient(180deg, #34d399 0%, #047857 100%);
    }

    .tabular-doc__chart-bar-fill--ticket {
        background: linear-gradient(180deg, #fbbf24 0%, #b45309 100%);
    }

    @media print {
        .tabular-doc__chart-bar--hourly[data-tip]:hover::after,
        .tabular-doc__chart-bar--hourly[data-tip]:focus-visible::after {
            display: none !important;
        }

        .tabular-doc__hourly-scroll {
            overflow: visible;
        }

        .tabular-doc__chart-bars--hourly {
            min-width: 0;
            width: 100%;
        }
    }

    @media print {
        .tabular-doc__chart-page:not(.tabular-doc__chart-page--only) {
            page-break-before: always;
        }
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

    .tabular-doc__pagamentos-row > td {
        background: #f8fafc !important;
        padding: 0.6mm 1.2mm 1.4mm !important;
        border-top: 0 !important;
    }

    .tabular-doc__pagamentos {
        display: flex;
        justify-content: flex-end;
        padding-left: 18%;
    }

    .tabular-doc__pagamentos-table {
        width: min(78mm, 100%);
        border-collapse: collapse;
        font-size: 9px;
    }

    .tabular-doc__pagamentos-table th,
    .tabular-doc__pagamentos-table td {
        border: 1px solid #9ca3af;
        padding: 0.45mm 0.9mm;
        vertical-align: top;
    }

    .tabular-doc__pagamentos-table thead th {
        background: #6b7280;
        color: #fff;
        font-weight: 700;
        text-align: left;
    }

    .tabular-doc__pagamentos-table .num {
        text-align: right;
        white-space: nowrap;
        width: 28mm;
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
