<style>
    .os-doc {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9pt;
        color: #111;
        line-height: 1.3;
    }

    .os-doc__frame {
        border: 1px solid #334155;
        padding: 6mm 6mm 5mm;
        background: #fff;
    }

    .os-doc__header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3mm;
    }

    .os-doc__header td {
        vertical-align: top;
        border: none;
        padding: 0;
    }

    .os-doc__logo {
        width: 22mm;
        height: 22mm;
        border: 1px solid #cbd5e1;
        text-align: center;
        vertical-align: middle;
        overflow: hidden;
    }

    .os-doc__logo img {
        max-width: 20mm;
        max-height: 20mm;
    }

    .os-doc__company-name {
        font-size: 11pt;
        font-weight: 700;
        margin: 0 0 1mm;
    }

    .os-doc__company-meta {
        font-size: 8pt;
        color: #334155;
        margin: 0;
    }

    .os-doc__title-box {
        text-align: right;
    }

    .os-doc__title {
        font-size: 14pt;
        font-weight: 800;
        letter-spacing: 0.3px;
        margin: 0;
        color: #0f2847;
    }

    .os-doc__os-num {
        font-size: 12pt;
        font-weight: 700;
        margin: 1mm 0 0;
    }

    .os-doc__status {
        display: inline-block;
        margin-top: 1.5mm;
        padding: 1px 6px;
        border: 1px solid #64748b;
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
    }

    .os-doc__meta {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3mm;
        font-size: 8pt;
    }

    .os-doc__meta td {
        border: 1px solid #94a3b8;
        padding: 2px 4px;
        width: 50%;
    }

    .os-doc__meta strong {
        color: #475569;
        font-weight: 600;
        margin-right: 3px;
    }

    .os-doc__section {
        margin: 0 0 3mm;
        page-break-inside: avoid;
    }

    .os-doc__section-title {
        background: #e8eef6;
        border: 1px solid #64748b;
        border-bottom: none;
        font-size: 8.5pt;
        font-weight: 800;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: #0f2847;
        padding: 2px 5px;
    }

    .os-doc__section-body {
        border: 1px solid #64748b;
        padding: 3px 5px;
        background: #fff;
    }

    .os-doc__kv {
        width: 100%;
        border-collapse: collapse;
    }

    .os-doc__kv td {
        border: none;
        padding: 1px 0;
        vertical-align: top;
        font-size: 8.5pt;
    }

    .os-doc__kv-label {
        width: 28mm;
        color: #64748b;
        font-weight: 600;
        white-space: nowrap;
    }

    .os-doc__equip {
        width: 100%;
    }

    .os-doc__equip-row {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 0;
    }

    .os-doc__equip-row + .os-doc__equip-row {
        margin-top: 1px;
    }

    .os-doc__equip-row td {
        border: none;
        padding: 0 4mm 0 0;
        vertical-align: top;
        font-size: 8.5pt;
        line-height: 1.25;
    }

    .os-doc__equip-row td:last-child {
        padding-right: 0;
    }

    .os-doc__equip-label {
        color: #64748b;
        font-weight: 600;
        margin-right: 3px;
    }

    .os-doc__text {
        white-space: pre-wrap;
        font-size: 9pt;
        margin: 0;
        min-height: 10mm;
    }

    .os-doc__table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 8pt;
    }

    .os-doc__table th,
    .os-doc__table td {
        border: 1px solid #94a3b8;
        padding: 2px 3px;
        vertical-align: top;
    }

    .os-doc__table th {
        background: #f1f5f9;
        font-weight: 700;
        text-align: left;
        color: #334155;
    }

    .os-doc__table .num {
        text-align: right;
        white-space: nowrap;
    }

    .os-doc__table .center {
        text-align: center;
        white-space: nowrap;
    }

    .os-doc__table tr {
        page-break-inside: avoid;
    }

    .os-doc__empty {
        color: #64748b;
        font-size: 8pt;
        font-style: italic;
        margin: 0;
    }

    .os-doc__totals-layout {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .os-doc__totals-layout td {
        border: none;
        padding: 0;
        vertical-align: top;
    }

    .os-doc__pagamentos-cell {
        width: auto;
        padding-right: 4mm !important;
    }

    .os-doc__totals-cell {
        width: 72mm;
    }

    .os-doc__pagamentos {
        border: 1px solid #94a3b8;
        padding: 2mm 3mm;
        min-height: 18mm;
        font-size: 8.5pt;
    }

    .os-doc__pagamentos-title {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 8pt;
        color: #334155;
        margin-bottom: 1.5mm;
        letter-spacing: 0.2px;
    }

    .os-doc__pagamento-item + .os-doc__pagamento-item {
        margin-top: 2mm;
        padding-top: 1.5mm;
        border-top: 1px dashed #cbd5e1;
    }

    .os-doc__pagamento-forma {
        font-size: 8.5pt;
        line-height: 1.35;
    }

    .os-doc__pagamento-parcelas {
        margin-top: 1mm;
        line-height: 1.45;
    }

    .os-doc__pagamento-parcela {
        display: inline-block;
        margin: 0 3mm 1mm 0;
        font-size: 8pt;
        color: #1e293b;
        white-space: nowrap;
    }

    .os-doc__pagamento-parcela-valor {
        color: #475569;
        font-size: 7.5pt;
    }

    .os-doc__totals {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
    }

    .os-doc__totals td {
        border: 1px solid #94a3b8;
        padding: 2px 4px;
    }

    .os-doc__totals td:last-child {
        text-align: right;
        font-weight: 700;
        white-space: nowrap;
    }

    .os-doc__totals tr.os-doc__totals-geral td {
        background: #e8eef6;
        font-weight: 800;
    }

    .os-doc__photos {
        width: 100%;
        border-collapse: collapse;
    }

    .os-doc__photos td {
        width: 33%;
        border: none;
        padding: 2mm;
        text-align: center;
        vertical-align: middle;
        page-break-inside: avoid;
    }

    .os-doc__photos img {
        max-width: 55mm;
        max-height: 45mm;
        width: auto;
        height: auto;
        border: 1px solid #cbd5e1;
    }

    .os-doc__sign-img {
        max-width: 70mm;
        max-height: 28mm;
        border: 1px solid #cbd5e1;
        background: #fff;
        display: block;
        margin-bottom: 2mm;
    }

    .os-doc__sign-row {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2mm;
    }

    .os-doc__sign-row td {
        width: 50%;
        border: none;
        padding: 0 4mm 0 0;
        vertical-align: top;
    }

    .os-doc__sign-line {
        border-top: 1px solid #334155;
        margin-top: 18mm;
        padding-top: 1mm;
        font-size: 8pt;
        color: #475569;
    }

    .os-doc__write-body {
        padding: 1mm 2mm 2mm;
    }

    .os-doc__write-lines {
        width: 100%;
        border-collapse: collapse;
    }

    .os-doc__write-lines td {
        border: none;
        border-bottom: 1px solid #94a3b8;
        height: 8mm;
        padding: 0;
        font-size: 9pt;
    }

    .os-doc--tecnica .os-doc__frame {
        padding: 3mm 4.5mm 2.5mm;
    }

    .os-doc--tecnica .os-doc__header {
        margin-bottom: 1.1mm;
    }

    .os-doc--tecnica .os-doc__title {
        font-size: 12.5pt;
    }

    .os-doc--tecnica .os-doc__os-num {
        font-size: 11pt;
        margin: 0.4mm 0 0;
    }

    .os-doc--tecnica .os-doc__status {
        margin-top: 1mm;
    }

    .os-doc--tecnica .os-doc__meta {
        margin-bottom: 1.1mm;
    }

    .os-doc--tecnica .os-doc__meta td {
        padding: 1px 3px;
    }

    .os-doc--tecnica .os-doc__section {
        margin: 0 0 1mm;
        page-break-inside: auto;
        break-inside: auto;
    }

    .os-doc--tecnica .os-doc__section-title {
        padding: 1px 4px;
        font-size: 8pt;
    }

    .os-doc--tecnica .os-doc__section-body {
        padding: 1px 3px;
    }

    .os-doc--tecnica .os-doc__kv td {
        padding: 0;
        font-size: 8pt;
        line-height: 1.15;
    }

    .os-doc--tecnica .os-doc__text {
        min-height: 3mm;
        font-size: 8.5pt;
        line-height: 1.2;
    }

    .os-doc--tecnica .os-doc__table th,
    .os-doc--tecnica .os-doc__table td {
        padding: 1px 3px;
    }

    .os-doc--tecnica .os-doc__write-body {
        padding: 0.3mm 1.5mm 0.4mm;
    }

    .os-doc--tecnica .os-doc__write-lines td {
        height: 5mm;
        font-size: 8.5pt;
    }

    .os-doc--tecnica .os-doc__section--write {
        page-break-inside: auto;
        break-inside: auto;
        page-break-before: auto;
        break-before: auto;
    }

    .os-doc--tecnica .os-doc__footer {
        margin-top: 1.5mm;
        padding-top: 1mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .os-doc__footer {
        margin-top: 4mm;
        border-top: 1px solid #94a3b8;
        padding-top: 2mm;
        font-size: 7.5pt;
        color: #64748b;
        width: 100%;
    }

    .os-doc__footer td {
        border: none;
        padding: 0;
    }
</style>
