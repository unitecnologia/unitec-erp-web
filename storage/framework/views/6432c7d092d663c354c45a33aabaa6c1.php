<style>
    .recibo-doc {
        font-family: Georgia, 'Times New Roman', Times, serif;
        color: #0f172a;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 18mm 16mm;
    }

    .recibo-doc__header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1.25rem;
        padding-bottom: 0.85rem;
        border-bottom: 2px solid #1e5a9e;
    }

    .recibo-doc__brand {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        min-width: 0;
    }

    .recibo-doc__logo {
        width: 52px;
        height: 52px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .recibo-doc__empresa {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f2847;
        line-height: 1.25;
    }

    .recibo-doc__endereco,
    .recibo-doc__meta {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.72rem;
        color: #475569;
        margin-top: 0.15rem;
        line-height: 1.35;
    }

    .recibo-doc__title-block {
        text-align: right;
        flex-shrink: 0;
    }

    .recibo-doc__title {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        color: #1e5a9e;
    }

    .recibo-doc__codigo {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.85rem;
        font-weight: 700;
        color: #334155;
        margin-top: 0.2rem;
    }

    .recibo-doc__valor-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 1.1rem 0 1.25rem;
        padding: 0.7rem 0.9rem;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
    }

    .recibo-doc__valor-label {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }

    .recibo-doc__valor {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }

    .recibo-doc__texto {
        margin: 0 0 2rem;
        font-size: 1.02rem;
        line-height: 1.7;
        text-align: justify;
    }

    .recibo-doc__rodape {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: end;
        margin-top: 2.5rem;
    }

    .recibo-doc__data {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.88rem;
        color: #334155;
    }

    .recibo-doc__assinatura {
        text-align: center;
    }

    .recibo-doc__linha {
        border-top: 1px solid #334155;
        margin-bottom: 0.35rem;
    }

    .recibo-doc__assinatura-label {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.75rem;
        color: #64748b;
    }
</style>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/reports/partials/recibo-document-styles.blade.php ENDPATH**/ ?>