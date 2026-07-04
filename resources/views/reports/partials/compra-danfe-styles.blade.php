<style>
    @page {
        margin: 5mm;
        size: A4 portrait;
    }

    .danfe {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 7pt;
        color: #000;
    }

    .danfe td,
    .danfe th {
        border: 1px solid #000;
        vertical-align: top;
        padding: 1px 2px;
    }

    .danfe__label {
        display: block;
        font-size: 5.5pt;
        line-height: 1.1;
        margin-bottom: 1px;
    }

    .danfe__value {
        display: block;
        font-size: 7pt;
        font-weight: 700;
        line-height: 1.15;
        word-break: break-word;
    }

    .danfe__value--normal {
        font-weight: 400;
    }

    .danfe__value--center {
        text-align: center;
    }

    .danfe__value--right {
        text-align: right;
    }

    .danfe__title {
        font-family: "Times New Roman", Times, serif;
        font-size: 16pt;
        font-weight: 700;
        text-align: center;
        line-height: 1;
    }

    .danfe__subtitle {
        font-size: 6.5pt;
        text-align: center;
        line-height: 1.1;
    }

    .danfe__nfe-box {
        border: 1px solid #000;
        text-align: center;
        padding: 2px;
    }

    .danfe__nfe-box-title {
        font-size: 10pt;
        font-weight: 700;
    }

    .danfe__tipo {
        display: inline-block;
        border: 1px solid #000;
        padding: 1px 4px;
        font-size: 7pt;
        font-weight: 700;
        margin-top: 2px;
    }

    .danfe__barcode {
        display: block;
        width: 100%;
        max-height: 48px;
        margin: 2px auto;
    }

    .danfe__chave {
        font-size: 6.5pt;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.02em;
        line-height: 1.2;
    }

    .danfe__portal {
        font-size: 5.5pt;
        text-align: center;
        line-height: 1.15;
    }

    .danfe__canhoto {
        font-size: 6pt;
        line-height: 1.2;
    }

    .danfe__section-title {
        font-size: 6pt;
        font-weight: 700;
        text-transform: uppercase;
        background: #f3f3f3;
    }

    .danfe__grid-total {
        width: 100%;
        border-collapse: collapse;
    }

    .danfe__grid-total td {
        border: 1px solid #000;
        padding: 1px 2px;
        vertical-align: top;
    }

    .danfe__items {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 6pt;
    }

    .danfe__items th {
        border: 1px solid #000;
        padding: 1px;
        font-size: 5.5pt;
        font-weight: 700;
        text-align: center;
        vertical-align: middle;
    }

    .danfe__items td {
        border-left: 1px solid #000;
        border-right: 1px solid #000;
        border-bottom: 1px dashed #666;
        padding: 1px 2px;
        vertical-align: top;
    }

    .danfe__item-cell {
        font-size: 6pt;
        font-weight: 700;
        line-height: 1.15;
    }

    .danfe__item-cell--desc {
        font-weight: 400;
        white-space: normal;
        word-wrap: break-word;
    }

    .danfe__item-cell--center {
        text-align: center;
    }

    .danfe__item-cell--right {
        text-align: right;
        white-space: nowrap;
    }

    .danfe__blank-row td {
        height: 12px;
        border-bottom: 1px dashed #666;
    }

    .danfe__info-box {
        min-height: 48px;
        font-size: 6pt;
        line-height: 1.25;
        white-space: pre-wrap;
    }
</style>
