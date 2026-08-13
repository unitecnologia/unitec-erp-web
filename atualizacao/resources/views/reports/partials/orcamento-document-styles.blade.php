<style>
    .orc-doc {
        font-family: "Courier New", Courier, monospace;
        font-size: 11px;
        color: #111;
        line-height: 1.35;
    }

    .orc-doc__frame {
        border: 1px solid #111;
        padding: 7mm 6mm;
        background: #fff;
    }

    .orc-doc__rule {
        height: 0;
        border: none;
        border-top: 1px solid #111;
        margin: 3mm 0;
    }

    .orc-doc__header {
        display: table;
        width: 100%;
    }

    .orc-doc__logo-cell,
    .orc-doc__company-cell {
        display: table-cell;
        vertical-align: top;
    }

    .orc-doc__logo-cell {
        width: 22mm;
        padding-right: 4mm;
    }

    .orc-doc__logo {
        width: 20mm;
        height: 20mm;
        border: 1px solid #bbb;
        text-align: center;
        vertical-align: middle;
    }

    .orc-doc__logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .orc-doc__logo-fallback {
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

    .orc-doc__company-name {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 1mm;
    }

    .orc-doc__title-row {
        display: table;
        width: 100%;
        margin-bottom: 2mm;
        font-size: 12px;
        font-weight: 700;
    }

    .orc-doc__title,
    .orc-doc__status {
        display: table-cell;
        vertical-align: middle;
    }

    .orc-doc__title {
        text-align: center;
    }

    .orc-doc__status {
        text-align: right;
        white-space: nowrap;
        width: 28mm;
    }

    .orc-doc__meta {
        margin-bottom: 1mm;
    }

    .orc-doc__meta-row {
        display: table;
        width: 100%;
        margin-bottom: 1mm;
    }

    .orc-doc__meta-row > span {
        display: table-cell;
        vertical-align: top;
    }

    .orc-doc__meta-row--split > span:first-child {
        width: 50%;
    }

    .orc-doc__table {
        width: 100%;
        border-collapse: collapse;
    }

    .orc-doc__table th,
    .orc-doc__table td {
        padding: 1.2mm 1mm;
        vertical-align: top;
    }

    .orc-doc__table thead th {
        border-bottom: 1px solid #111;
        font-size: 10px;
        text-align: left;
    }

    .orc-doc__table td.num,
    .orc-doc__table th.num {
        text-align: right;
        white-space: nowrap;
    }

    .orc-doc__table td.center,
    .orc-doc__table th.center {
        text-align: center;
    }

    .orc-doc__table td.produto {
        word-break: break-word;
    }

    .orc-doc__totals {
        display: table;
        width: 100%;
        font-weight: 700;
    }

    .orc-doc__totals > span {
        display: table-cell;
        width: 33.33%;
        vertical-align: top;
    }

    .orc-doc__obs-title {
        font-weight: 700;
        margin-bottom: 1.5mm;
    }

    .orc-doc__obs-text {
        min-height: 16mm;
        white-space: pre-wrap;
    }
</style>
