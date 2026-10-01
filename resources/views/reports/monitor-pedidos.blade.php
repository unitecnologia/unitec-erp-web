<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — {{ $reportTitle }}</title>
    <style>
        @page {
            margin: 10mm 13mm 14mm 10mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #c7d5e8;
        }

        .viewer {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .viewer__toolbar {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
            padding: 0.35rem 0.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
            border-bottom: 1px solid #94a3b8;
        }

        .viewer__title {
            margin-right: auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            color: #0f2847;
        }

        .viewer__filters {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-right: 0.35rem;
            padding-right: 0.55rem;
            border-right: 1px solid #94a3b8;
        }

        .viewer__ord {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.74rem;
            font-weight: 700;
            color: #0f2847;
        }

        .viewer__ord select {
            min-height: 1.7rem;
            padding: 0.15rem 0.35rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: #fff;
            font-size: 0.74rem;
            font-weight: 600;
            color: #0f172a;
        }

        .viewer__btn {
            min-width: 5.5rem;
            padding: 0.28rem 0.65rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        .viewer__btn:hover {
            border-color: #1e5a9e;
            background: #ffffff;
        }

        .viewer__btn--close {
            background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #fca5a5;
        }

        .viewer__canvas {
            flex: 1;
            overflow: auto;
            padding: 1rem;
        }

        .viewer__paper {
            width: min(210mm, 100%);
            margin: 0 auto;
            background: #fff;
            padding: 8mm;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
        }

        @media print {
            body {
                background: #fff;
            }

            .viewer__toolbar {
                display: none !important;
            }

            .viewer__canvas {
                padding: 0;
                overflow: visible;
            }

            .viewer__paper {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
    @include('reports.partials.monitor-pedidos-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar — {{ $reportTitle }}@if (filled($cargaCabecalho ?? null)) — {{ $cargaCabecalho }}@endif</span>

            <div class="viewer__filters">
                <label class="viewer__ord">
                    Ordenar por
                    <select id="filtro-ord" onchange="applyFilters()">
                        @foreach (($ordenacaoLabels ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($ordenacao ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__canvas">
            <div class="viewer__paper">
                @include('reports.partials.monitor-pedidos-document-body')
            </div>
        </div>
    </div>

    <script>
        const reportUrl = @json($reportUrl);
        const closeUrl = @json($closeUrl ?? null);
        const baseQuery = @json($queryParams ?? []);

        function currentFilters() {
            return Object.assign({}, baseQuery, {
                ord: document.getElementById('filtro-ord')?.value || 'alfabetica',
            });
        }

        function applyFilters() {
            const params = currentFilters();
            window.location.href = reportUrl + '?' + new URLSearchParams(params).toString();
        }

        function savePdf() {
            const params = Object.assign(currentFilters(), { pdf: 1 });
            window.open(reportUrl + '?' + new URLSearchParams(params).toString(), '_blank');
        }

        function closePreview() {
            // F6 abre em nova aba (window.open): fechar sem remountar a lista do opener.
            if (window.opener && ! window.opener.closed) {
                window.close();
                return;
            }

            if (closeUrl) {
                window.location.assign(closeUrl);
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.close();
        }

        function mmToPx(mm) {
            return (mm * 96) / 25.4;
        }

        function clearMonitorPageLayers() {
            document.querySelectorAll('.monitor-pedidos-doc__page-layer').forEach((el) => el.remove());
        }

        function syncMonitorPageLabels(totalPages) {
            const total = Math.max(1, totalPages || 1);
            document.querySelectorAll('.monitor-pedidos-doc__footer-page').forEach((el) => {
                el.textContent = 'Pag: 1 de ' + total;
            });
        }

        /** Altura do conteúdo imprimível — sem padding de .viewer__paper. */
        function measureMonitorContentHeightPx() {
            const doc = document.querySelector('.monitor-pedidos-doc');
            if (! doc) {
                return 0;
            }

            return Math.max(doc.scrollHeight || 0, doc.offsetHeight || 0);
        }

        /** Área útil A4 (margens 10mm / 14mm inferior). */
        function monitorPrintPageHeightPx() {
            return Math.max(1, Math.floor(mmToPx(297 - 10 - 14)));
        }

        /**
         * pageCount a partir do conteúdo real.
         * Slack ~1mm só para ruído mm→px; não mascara conteúdo > 1 página.
         */
        function computeMonitorPageCount(contentHeightPx, pageHeightPx) {
            const height = Math.max(0, contentHeightPx || 0);
            const pageH = Math.max(1, pageHeightPx || 1);
            const slackPx = mmToPx(1);

            if (height <= pageH + slackPx) {
                return 1;
            }

            return Math.max(1, Math.ceil((height - slackPx) / pageH));
        }

        function prepareMonitorPageNumbers() {
            clearMonitorPageLayers();

            const paper = document.querySelector('.viewer__paper');
            const doc = document.querySelector('.monitor-pedidos-doc');
            if (! paper || ! doc) {
                syncMonitorPageLabels(1);
                return;
            }

            const pageHeightPx = monitorPrintPageHeightPx();
            const contentHeightPx = measureMonitorContentHeightPx();
            const totalPages = computeMonitorPageCount(contentHeightPx, pageHeightPx);

            syncMonitorPageLabels(totalPages);

            const previousPosition = paper.style.position;
            if (! previousPosition) {
                paper.style.position = 'relative';
            }
            paper.dataset.erpPrevPosition = previousPosition;

            const footerOffsetPx = mmToPx(8);
            // Última página: alinhar na linha do Pag (abaixo do crédito), não 8mm acima.
            const anchors = document.querySelectorAll('.monitor-pedidos-doc__footer-page');
            const lastAnchor = anchors.length ? anchors[anchors.length - 1] : null;
            let lastPageTopPx = Math.max(0, contentHeightPx - mmToPx(2));
            if (lastAnchor) {
                const paperRect = paper.getBoundingClientRect();
                const anchorRect = lastAnchor.getBoundingClientRect();
                lastPageTopPx = Math.max(0, (anchorRect.top - paperRect.top) + (paper.scrollTop || 0));
            }

            for (let page = 1; page <= totalPages; page++) {
                const layer = document.createElement('div');
                layer.className = 'monitor-pedidos-doc__page-layer';
                layer.textContent = 'Pag: ' + page + ' de ' + totalPages;
                const rawTop = (page * pageHeightPx) - footerOffsetPx;
                const topPx = (page === totalPages)
                    ? Math.min(rawTop, lastPageTopPx)
                    : rawTop;
                layer.style.top = Math.max(0, topPx) + 'px';
                paper.appendChild(layer);
            }
        }

        function resetMonitorPageNumbers() {
            const paper = document.querySelector('.viewer__paper');
            clearMonitorPageLayers();
            if (paper && Object.prototype.hasOwnProperty.call(paper.dataset, 'erpPrevPosition')) {
                paper.style.position = paper.dataset.erpPrevPosition || '';
                delete paper.dataset.erpPrevPosition;
            }
        }

        window.addEventListener('beforeprint', prepareMonitorPageNumbers);
        window.addEventListener('afterprint', resetMonitorPageNumbers);

        window.addEventListener('load', () => {
            if (! document.querySelector('.monitor-pedidos-doc')) {
                syncMonitorPageLabels(1);
                return;
            }
            syncMonitorPageLabels(
                computeMonitorPageCount(measureMonitorContentHeightPx(), monitorPrintPageHeightPx())
            );
        });

        @if ($autoPrint)
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        @endif
    </script>
</body>
</html>
