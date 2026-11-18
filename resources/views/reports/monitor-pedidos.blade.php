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
        const impDuasVias = @json((bool) ($impDuasVias ?? false));
        const impMeiaFolhaA4 = @json((bool) ($impMeiaFolhaA4 ?? false));

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
            // PDF permanece sempre 1 via / A4 normal (params de vias e meia folha são só da impressão).
            const params = Object.assign(currentFilters(), { pdf: 1 });
            window.open(reportUrl + '?' + new URLSearchParams(params).toString(), '_blank');
        }

        function closePreview() {
            // Overlay interno (TV Listar Vendas / embeds): fecha sem navegar a aba pai.
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-monitor-pedidos-preview-close' }, '*');
                return;
            }

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

        function clearMonitorPageLayers(root) {
            const scope = root || document;
            scope.querySelectorAll('.monitor-pedidos-doc__page-layer').forEach((el) => el.remove());
        }

        function syncMonitorPageLabels(doc, totalPages) {
            const total = Math.max(1, totalPages || 1);
            const scope = doc || document;
            scope.querySelectorAll('.monitor-pedidos-doc__footer-page').forEach((el) => {
                el.textContent = 'Pag: 1 de ' + total;
            });
        }

        /** Altura do conteúdo imprimível de uma via. */
        function measureMonitorContentHeightPx(doc) {
            const target = doc || document.querySelector('.monitor-pedidos-doc');
            if (! target) {
                return 0;
            }

            return Math.max(target.scrollHeight || 0, target.offsetHeight || 0);
        }

        /** Área útil A4 (margens 10mm / 14mm inferior). */
        function monitorPrintPageHeightPx() {
            return Math.max(1, Math.floor(mmToPx(297 - 10 - 14)));
        }

        /** Metade da área útil A4 (região A5 / meia folha). */
        function monitorPrintHalfPageHeightPx() {
            return Math.max(1, Math.floor(monitorPrintPageHeightPx() / 2));
        }

        /**
         * Altura do slot de cada via na meia folha.
         * Com 2 vias, reserva faixa central para o corte (tesoura + espaço).
         */
        function monitorPrintMeiaFolhaSlotHeightPx(comDuasVias) {
            const pageH = monitorPrintPageHeightPx();
            if (! comDuasVias) {
                return Math.max(1, Math.floor(pageH / 2));
            }

            // Reserva realista da faixa de corte (margens CSS + ícone); evita pular para 2ª folha.
            const cutGapPx = mmToPx(16);
            return Math.max(1, Math.floor((pageH - cutGapPx) / 2));
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

        /** Encosta o início da via no topo da folha quando o resto é só ruído de medida. */
        function snapMonitorFlowOffset(offsetPx, pageHeightPx) {
            const offset = Math.max(0, offsetPx || 0);
            const pageH = Math.max(1, pageHeightPx || 1);
            const slackPx = mmToPx(2);
            const remainder = offset % pageH;

            if (remainder <= slackPx) {
                return offset - remainder;
            }

            if ((pageH - remainder) <= slackPx) {
                return offset + (pageH - remainder);
            }

            return offset;
        }

        function monitorDocFlowOffsetPx(doc) {
            const paper = doc.closest('.viewer__paper');
            const origin = paper?.querySelector('.monitor-pedidos-doc:not([data-erp-via-clone])');
            if (! origin || doc === origin) {
                return 0;
            }

            return Math.max(0, doc.getBoundingClientRect().top - origin.getBoundingClientRect().top);
        }

        /**
         * Páginas que a via ocupa quando ela não começa no topo da folha.
         * Slack de 1mm, igual a computeMonitorPageCount.
         */
        function computeMonitorPageSpan(contentHeightPx, pageHeightPx, flowOffsetPx) {
            const height = Math.max(0, contentHeightPx || 0);
            const pageH = Math.max(1, pageHeightPx || 1);
            const slackPx = mmToPx(1);
            const start = Math.max(0, flowOffsetPx || 0);
            const end = start + Math.max(0, height - slackPx);
            const firstPhys = Math.floor(start / pageH);
            let lastPhys = Math.floor(Math.max(start, end - 0.01) / pageH);

            if (lastPhys < firstPhys) {
                lastPhys = firstPhys;
            }

            return {
                firstPhys: firstPhys,
                totalPages: (lastPhys - firstPhys) + 1,
            };
        }

        function ensureViaBanner(doc, label) {
            if (! doc) return;
            let banner = doc.querySelector(':scope > .monitor-pedidos-doc__via-banner');
            if (! banner) {
                banner = document.createElement('div');
                banner.className = 'monitor-pedidos-doc__via-banner';
                doc.insertBefore(banner, doc.firstChild);
            }
            banner.style.display = 'block';
            banner.textContent = label;
        }

        function clearViaBanners(doc) {
            if (! doc) return;
            doc.querySelectorAll(':scope > .monitor-pedidos-doc__via-banner').forEach((el) => el.remove());
        }

        function buildViaSeparator() {
            const sep = document.createElement('div');
            sep.className = 'monitor-pedidos-doc__cut monitor-pedidos-doc__via-sep';
            sep.setAttribute('data-erp-via-clone', '1');
            sep.setAttribute('aria-hidden', 'true');
            sep.innerHTML = '<span class="monitor-pedidos-doc__cut-icon">✂</span>'
                + '<span class="monitor-pedidos-doc__cut-line"></span>'
                + '<span class="monitor-pedidos-doc__cut-label">corte</span>'
                + '<span class="monitor-pedidos-doc__cut-line"></span>';

            return sep;
        }

        /**
         * Duplica o DOM já montado só na impressão (1 query / 1 build de blocos).
         * Via do estabelecimento e, logo abaixo, via do cliente — mesma folha se couber.
         */
        function prepareDualViasForPrint() {
            if (! impDuasVias) {
                resetDualViasAfterPrint();
                return;
            }

            const paper = document.querySelector('.viewer__paper');
            const original = paper?.querySelector('.monitor-pedidos-doc:not([data-erp-via-clone])');
            if (! paper || ! original) {
                return;
            }

            paper.querySelectorAll('[data-erp-via-clone]').forEach((el) => el.remove());

            ensureViaBanner(original, 'VIA DO ESTABELECIMENTO');

            const clone = original.cloneNode(true);
            clone.setAttribute('data-erp-via-clone', '1');
            clone.classList.add('monitor-pedidos-doc--via-cliente');
            ensureViaBanner(clone, 'VIA DO CLIENTE');
            clearMonitorPageLayers(clone);
            paper.appendChild(buildViaSeparator());
            paper.appendChild(clone);
            paper.classList.add('viewer__paper--duas-vias');
            document.body.classList.add('erp-print-duas-vias');
        }

        function resetDualViasAfterPrint() {
            const paper = document.querySelector('.viewer__paper');
            if (! paper) {
                return;
            }

            paper.classList.remove('viewer__paper--duas-vias');
            document.body.classList.remove('erp-print-duas-vias');
            paper.querySelectorAll('[data-erp-via-clone]').forEach((el) => el.remove());
            paper.querySelectorAll('.monitor-pedidos-doc:not([data-erp-via-clone])').forEach((doc) => {
                clearViaBanners(doc);
            });
        }

        let meiaFolhaAplicada = false;

        function clearMeiaFolhaBlankRows(root) {
            const scope = root || document;
            scope.querySelectorAll('[data-erp-meia-folha-blank]').forEach((el) => el.remove());
        }

        function findMeiaFolhaItemsTable(block) {
            if (! block) {
                return null;
            }

            const keepEnd = block.querySelector('.monitor-pedidos-doc__keep-end');
            if (keepEnd) {
                let el = keepEnd.previousElementSibling;
                while (el) {
                    if (el.matches && el.matches('table.monitor-pedidos-doc__table')) {
                        return el;
                    }
                    el = el.previousElementSibling;
                }
            }

            return block.querySelector('.monitor-pedidos-doc__keep-start table.monitor-pedidos-doc__table');
        }

        function findMeiaFolhaSampleItemRow(table) {
            if (! table) {
                return null;
            }

            const rows = table.querySelectorAll('tr');
            for (let i = 0; i < rows.length; i++) {
                const tr = rows[i];
                if (tr.querySelector('th')) {
                    continue;
                }
                if (tr.classList.contains('monitor-pedidos-doc__totals-row')) {
                    continue;
                }
                if (tr.hasAttribute('data-erp-meia-folha-blank')) {
                    continue;
                }
                if (tr.querySelector('td[colspan]')) {
                    continue;
                }
                if (tr.querySelector('td')) {
                    return tr;
                }
            }

            return null;
        }

        function buildMeiaFolhaBlankRow(sampleRow, table) {
            const tr = document.createElement('tr');
            tr.className = 'monitor-pedidos-doc__blank-row';
            tr.setAttribute('data-erp-meia-folha-blank', '1');

            if (sampleRow) {
                sampleRow.querySelectorAll('td').forEach((td) => {
                    const cell = document.createElement('td');
                    cell.className = td.className;
                    cell.innerHTML = '&nbsp;';
                    tr.appendChild(cell);
                });
                return tr;
            }

            const headCells = table
                ? table.querySelectorAll('tr.monitor-pedidos-doc__table-head th')
                : [];
            if (headCells.length) {
                headCells.forEach((th) => {
                    const cell = document.createElement('td');
                    cell.className = th.className || '';
                    cell.innerHTML = '&nbsp;';
                    tr.appendChild(cell);
                });
                return tr;
            }

            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.innerHTML = '&nbsp;';
            tr.appendChild(cell);
            return tr;
        }

        /**
         * Completa a grade de itens com linhas em branco até a altura alvo (meia folha).
         * Totais/rodapé descem com a grade. Se já passa do alvo, não altera.
         */
        function padMeiaFolhaItemsToHalf(doc, targetHeightPx) {
            if (! doc) {
                return false;
            }

            clearMeiaFolhaBlankRows(doc);

            // Folga maior: não encostar no limite da meia folha (impressora/zoom costuma estourar).
            const slackPx = mmToPx(2.5);
            const fillTargetPx = Math.max(1, targetHeightPx - mmToPx(2));
            const naturalH = measureMonitorContentHeightPx(doc);
            if (naturalH > targetHeightPx + slackPx) {
                return false;
            }
            if (naturalH >= fillTargetPx - slackPx) {
                return true;
            }

            const blocks = doc.querySelectorAll('.monitor-pedidos-doc__block');
            const block = blocks.length ? blocks[blocks.length - 1] : null;
            const table = findMeiaFolhaItemsTable(block);
            if (! table) {
                return false;
            }

            const sampleRow = findMeiaFolhaSampleItemRow(table);
            const probe = buildMeiaFolhaBlankRow(sampleRow, table);
            table.appendChild(probe);
            const rowH = Math.max(
                1,
                Math.round(probe.getBoundingClientRect().height || probe.offsetHeight || 0),
            );

            let deficit = fillTargetPx - measureMonitorContentHeightPx(doc);
            let added = 1;
            const maxRows = 80;

            while (deficit > slackPx && added < maxRows) {
                if (deficit < rowH * 0.35) {
                    break;
                }
                table.appendChild(buildMeiaFolhaBlankRow(sampleRow, table));
                added++;
                deficit = fillTargetPx - measureMonitorContentHeightPx(doc);
            }

            while (measureMonitorContentHeightPx(doc) > fillTargetPx + slackPx && added > 0) {
                const blanks = table.querySelectorAll('[data-erp-meia-folha-blank]');
                if (! blanks.length) {
                    break;
                }
                blanks[blanks.length - 1].remove();
                added--;
            }

            return measureMonitorContentHeightPx(doc) <= targetHeightPx + slackPx + mmToPx(2);
        }

        /**
         * Meia folha A4: preenche a grade com linhas em branco até metade da área útil.
         * Roda após o clone das 2 vias (banner já conta na altura). Se não couber, impressão normal.
         */
        function prepareMeiaFolhaPads() {
            meiaFolhaAplicada = false;
            clearMeiaFolhaBlankRows(document);

            if (! impMeiaFolhaA4) {
                return;
            }

            const paper = document.querySelector('.viewer__paper');
            const docs = paper
                ? paper.querySelectorAll('.monitor-pedidos-doc')
                : [];
            if (! paper || ! docs.length) {
                return;
            }

            const comDuasVias = impDuasVias && docs.length >= 2;
            const slotH = monitorPrintMeiaFolhaSlotHeightPx(comDuasVias);
            let ok = true;
            docs.forEach((doc) => {
                if (! padMeiaFolhaItemsToHalf(doc, slotH)) {
                    ok = false;
                }
            });

            if (! ok) {
                clearMeiaFolhaBlankRows(document);
                return;
            }

            meiaFolhaAplicada = true;
            docs.forEach((doc) => {
                doc.classList.add('monitor-pedidos-doc--meia-folha-slot');
                doc.dataset.erpMeiaFolha = '1';
            });
        }

        /** Classes/chrome de meia folha depois do clone das vias. */
        function prepareMeiaFolhaChrome() {
            if (! meiaFolhaAplicada) {
                return;
            }

            const paper = document.querySelector('.viewer__paper');
            if (! paper) {
                return;
            }

            const docs = paper.querySelectorAll('.monitor-pedidos-doc');
            const comDuasVias = impDuasVias && docs.length >= 2;
            const slotH = monitorPrintMeiaFolhaSlotHeightPx(comDuasVias);
            document.body.classList.add('erp-print-meia-folha');
            paper.classList.add('viewer__paper--meia-folha');

            docs.forEach((doc) => {
                doc.classList.add('monitor-pedidos-doc--meia-folha-slot');
                doc.dataset.erpMeiaFolha = '1';
                doc.style.minHeight = slotH + 'px';
            });

            if (comDuasVias) {
                paper.classList.add('viewer__paper--meia-folha-duas');
                const sep = paper.querySelector('.monitor-pedidos-doc__via-sep');
                if (sep) {
                    sep.classList.add('monitor-pedidos-doc__via-sep--meia-folha');
                }
            }
        }

        function resetMeiaFolhaAfterPrint() {
            const paper = document.querySelector('.viewer__paper');
            document.body.classList.remove('erp-print-meia-folha');
            meiaFolhaAplicada = false;
            clearMeiaFolhaBlankRows(document);

            if (! paper) {
                return;
            }

            paper.classList.remove('viewer__paper--meia-folha', 'viewer__paper--meia-folha-duas');
            paper.querySelectorAll('.monitor-pedidos-doc--meia-folha-slot').forEach((doc) => {
                doc.classList.remove('monitor-pedidos-doc--meia-folha-slot');
                doc.style.minHeight = '';
                delete doc.dataset.erpMeiaFolha;
            });
            paper.querySelectorAll('.monitor-pedidos-doc__via-sep--meia-folha').forEach((el) => {
                el.classList.remove('monitor-pedidos-doc__via-sep--meia-folha');
            });
        }
        function prepareMonitorPageNumbersForDoc(doc, flowOffsetPx) {
            if (! doc) {
                return;
            }

            clearMonitorPageLayers(doc);

            const pageHeightPx = monitorPrintPageHeightPx();
            const contentHeightPx = measureMonitorContentHeightPx(doc);
            const offsetPx = snapMonitorFlowOffset(flowOffsetPx || 0, pageHeightPx);
            const startsMidPage = offsetPx > mmToPx(1);
            const span = startsMidPage
                ? computeMonitorPageSpan(contentHeightPx, pageHeightPx, offsetPx)
                : null;
            const totalPages = span
                ? span.totalPages
                : computeMonitorPageCount(contentHeightPx, pageHeightPx);

            syncMonitorPageLabels(doc, totalPages);

            const previousPosition = doc.style.position;
            if (! previousPosition || previousPosition === 'static') {
                doc.style.position = 'relative';
            }
            doc.dataset.erpPrevPosition = previousPosition || '';

            const footerOffsetPx = mmToPx(8);
            const anchors = doc.querySelectorAll('.monitor-pedidos-doc__footer-page');
            const lastAnchor = anchors.length ? anchors[anchors.length - 1] : null;
            let lastPageTopPx = Math.max(0, contentHeightPx - mmToPx(2));
            if (lastAnchor) {
                const docRect = doc.getBoundingClientRect();
                const anchorRect = lastAnchor.getBoundingClientRect();
                lastPageTopPx = Math.max(0, (anchorRect.top - docRect.top) + (doc.scrollTop || 0));
            }

            let lastPlacedPx = -1;

            for (let page = 1; page <= totalPages; page++) {
                const layer = document.createElement('div');
                layer.className = 'monitor-pedidos-doc__page-layer';
                layer.textContent = 'Pag: ' + page + ' de ' + totalPages;
                const rawTop = span
                    ? (((span.firstPhys + page) * pageHeightPx) - footerOffsetPx - offsetPx)
                    : ((page * pageHeightPx) - footerOffsetPx);
                let topPx = (page === totalPages)
                    ? Math.min(rawTop, lastPageTopPx)
                    : rawTop;
                if (span) {
                    topPx = Math.min(topPx, lastPageTopPx);
                }
                topPx = Math.max(0, topPx);
                if (lastPlacedPx >= 0 && Math.abs(topPx - lastPlacedPx) < 4) {
                    continue;
                }
                lastPlacedPx = topPx;
                layer.style.top = topPx + 'px';
                doc.appendChild(layer);
            }
        }

        function prepareMonitorPageNumbers() {
            // Clone das vias primeiro; depois completa a grade nas duas com a mesma lógica.
            prepareDualViasForPrint();
            prepareMeiaFolhaPads();
            prepareMeiaFolhaChrome();

            const docs = document.querySelectorAll('.viewer__paper .monitor-pedidos-doc');
            if (! docs.length) {
                syncMonitorPageLabels(document, 1);
                return;
            }

            docs.forEach((doc) => {
                const offset = (impDuasVias || document.body.classList.contains('erp-print-meia-folha'))
                    ? monitorDocFlowOffsetPx(doc)
                    : 0;
                prepareMonitorPageNumbersForDoc(doc, offset);
            });
        }

        function resetMonitorPageNumbers() {
            document.querySelectorAll('.viewer__paper .monitor-pedidos-doc').forEach((doc) => {
                clearMonitorPageLayers(doc);
                if (Object.prototype.hasOwnProperty.call(doc.dataset, 'erpPrevPosition')) {
                    doc.style.position = doc.dataset.erpPrevPosition || '';
                    delete doc.dataset.erpPrevPosition;
                }
            });
            resetMeiaFolhaAfterPrint();
            resetDualViasAfterPrint();
        }

        window.addEventListener('beforeprint', prepareMonitorPageNumbers);
        window.addEventListener('afterprint', resetMonitorPageNumbers);

        window.addEventListener('load', () => {
            const doc = document.querySelector('.monitor-pedidos-doc');
            if (! doc) {
                syncMonitorPageLabels(document, 1);
                return;
            }
            syncMonitorPageLabels(
                doc,
                computeMonitorPageCount(measureMonitorContentHeightPx(doc), monitorPrintPageHeightPx())
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
