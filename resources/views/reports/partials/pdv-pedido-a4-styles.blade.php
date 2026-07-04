<style>
    .pdv-pedido-a4 {
        font-family: "Courier New", Courier, monospace;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .pdv-pedido-a4__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .pdv-pedido-a4__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .pdv-pedido-a4__header {
        display: table;
        width: 100%;
    }

    .pdv-pedido-a4__logo-cell,
    .pdv-pedido-a4__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .pdv-pedido-a4__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .pdv-pedido-a4__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .pdv-pedido-a4__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .pdv-pedido-a4__logo-fallback {
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

    .pdv-pedido-a4__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .pdv-pedido-a4__meta {
        margin-bottom: 1mm;
    }

    .pdv-pedido-a4__meta-row {
        display: table;
        width: 100%;
        margin-bottom: 1mm;
    }

    .pdv-pedido-a4__meta-row > span {
        display: table-cell;
        vertical-align: top;
    }

    .pdv-pedido-a4__meta-row--split > span:first-child {
        width: 50%;
    }

    .pdv-pedido-a4__meta-row--split > span:last-child {
        text-align: right;
    }

    .pdv-pedido-a4__table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdv-pedido-a4__table th,
    .pdv-pedido-a4__table td {
        padding: 1.2mm 1mm;
        vertical-align: top;
        border-right: 1px solid #111;
    }

    .pdv-pedido-a4__table th:last-child,
    .pdv-pedido-a4__table td:last-child {
        border-right: none;
    }

    .pdv-pedido-a4__table thead th {
        border-bottom: 1px solid #111;
        font-size: 10px;
        text-align: left;
    }

    .pdv-pedido-a4__table td.num,
    .pdv-pedido-a4__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .pdv-pedido-a4__table td.center,
    .pdv-pedido-a4__table th.center {
        text-align: center;
    }

    .pdv-pedido-a4__table td.produto {
        word-break: break-word;
    }

    .pdv-pedido-a4__totals {
        display: table;
        width: 100%;
        font-weight: 700;
    }

    .pdv-pedido-a4__totals > span {
        display: table-cell;
        width: 33.33%;
        vertical-align: top;
    }

    .pdv-pedido-a4__totals > span:nth-child(2) {
        text-align: center;
    }

    .pdv-pedido-a4__totals > span:nth-child(3) {
        text-align: right;
    }

    .pdv-pedido-a4__obs-title {
        font-weight: 700;
        margin-bottom: 1.5mm;
    }

    .pdv-pedido-a4__obs-text {
        min-height: 10mm;
        white-space: pre-wrap;
    }

    .pdv-pedido-a4__footer {
        display: table;
        width: 100%;
        margin-top: 8mm;
    }

    .pdv-pedido-a4__footer-decl,
    .pdv-pedido-a4__footer-sign {
        display: table-cell;
        vertical-align: bottom;
    }

    .pdv-pedido-a4__footer-decl {
        width: 65%;
        padding-right: 6mm;
    }

    .pdv-pedido-a4__footer-sign {
        width: 35%;
        text-align: center;
    }

    .pdv-pedido-a4__sign-line {
        border-top: 1px solid #111;
        margin-bottom: 1mm;
    }

    .pdv-pedido-a4__via-label {
        text-align: center;
        font-size: 10px;
        margin: 0 0 6mm;
    }
</style>
