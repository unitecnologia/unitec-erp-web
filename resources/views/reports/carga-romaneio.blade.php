@php
    $queryParams = $queryParams ?? [
        'cliente' => ! empty($exibirCliente) ? 1 : 0,
        'valor' => ! empty($exibirValor) ? 1 : 0,
        'ord' => $ordenacao ?? 'quantidade',
    ];

    $buildUrl = function (array $extra = []) use ($reportUrl, $queryParams): string {
        return $reportUrl.'?'.http_build_query(array_merge($queryParams, $extra));
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Romaneio de Carga</title>
    <style>
        @page { margin: 10mm 10mm 14mm 10mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; padding: 0.4rem 0.55rem; background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%); border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__filters { display: inline-flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; margin-right: 0.35rem; padding-right: 0.55rem; border-right: 1px solid #94a3b8; }
        .viewer__check { display: inline-flex; align-items: center; gap: 0.28rem; font-size: 0.74rem; font-weight: 700; color: #0f2847; cursor: pointer; user-select: none; }
        .viewer__check input { width: 0.95rem; height: 0.95rem; cursor: pointer; }
        .viewer__ord { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.74rem; font-weight: 700; color: #0f2847; }
        .viewer__ord select { min-height: 1.7rem; padding: 0.15rem 0.35rem; border: 1px solid #94a3b8; border-radius: 4px; background: #fff; font-size: 0.74rem; font-weight: 600; color: #0f172a; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%); color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__btn:hover { border-color: #1e5a9e; background: #ffffff; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__canvas { flex: 1; overflow: auto; padding: 1rem; }
        .viewer__paper { width: min(210mm, 100%); margin: 0 auto; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar { display: none; }
            .viewer__canvas { padding: 0; overflow: visible; }
            .viewer__paper { width: 100%; }
            .pessoa-list-doc__frame { border: none; padding: 0; }
        }
    </style>
    @include('reports.partials.pessoas-listagem-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar</span>

            <div class="viewer__filters">
                <label class="viewer__check">
                    <input
                        type="checkbox"
                        id="filtro-cliente"
                        @checked($exibirCliente)
                        onchange="applyFilters()"
                    >
                    Exibir cliente
                </label>
                <label class="viewer__check">
                    <input
                        type="checkbox"
                        id="filtro-valor"
                        @checked($exibirValor)
                        onchange="applyFilters()"
                    >
                    Exibir valor
                </label>
                <label class="viewer__ord">
                    Ordenar
                    <select id="filtro-ord" onchange="applyFilters()">
                        @foreach ($ordenacaoLabels as $value => $label)
                            <option value="{{ $value }}" @selected($ordenacao === $value)>{{ $label }}</option>
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
                @include('reports.partials.carga-romaneio-document-body')
            </div>
        </div>
    </div>

    <script>
        function currentFilters() {
            const params = {
                cliente: document.getElementById('filtro-cliente')?.checked ? 1 : 0,
                valor: document.getElementById('filtro-valor')?.checked ? 1 : 0,
                ord: document.getElementById('filtro-ord')?.value || 'quantidade',
            };

            @if (! empty($queryParams['ids']))
                params.ids = @json((string) $queryParams['ids']);
            @endif

            return params;
        }

        function applyFilters() {
            const params = currentFilters();
            window.location.href = @json($reportUrl) + '?' + new URLSearchParams(params).toString();
        }

        function savePdf() {
            const params = Object.assign(currentFilters(), { pdf: 1 });
            window.open(@json($reportUrl) + '?' + new URLSearchParams(params).toString(), '_blank');
        }

        function closePreview() {
            // Sempre sai para Carga/Romaneio — não usa history.back()
            // (flags/ordenação recarregam a URL e poluem o histórico).
            window.location.href = @json($closeUrl);
        }

        function mmToPx(mm) {
            return (mm * 96) / 25.4;
        }

        function clearRomaneioPageLayers() {
            document.querySelectorAll('.carga-romaneio__page-layer').forEach((el) => el.remove());
        }

        function syncRomaneioPageLabels(totalPages) {
            const total = Math.max(1, totalPages || 1);
            document.querySelectorAll('.carga-romaneio__footer-page').forEach((el) => {
                el.textContent = 'Pag: 1 de ' + total;
            });
        }

        function prepareRomaneioPageNumbers() {
            clearRomaneioPageLayers();

            const paper = document.querySelector('.viewer__paper');
            if (! paper) {
                syncRomaneioPageLabels(1);
                return;
            }

            // Área útil A4 com margens 10mm / 14mm (inferior).
            const pageHeightPx = Math.max(1, Math.floor(mmToPx(297 - 10 - 14)));
            const totalPages = Math.max(1, Math.ceil(paper.scrollHeight / pageHeightPx));

            syncRomaneioPageLabels(totalPages);

            const previousPosition = paper.style.position;
            if (! previousPosition) {
                paper.style.position = 'relative';
            }

            for (let page = 1; page <= totalPages; page++) {
                const layer = document.createElement('div');
                layer.className = 'carga-romaneio__page-layer';
                layer.textContent = 'Pag: ' + page + ' de ' + totalPages;
                layer.style.top = ((page * pageHeightPx) - mmToPx(8)) + 'px';
                paper.appendChild(layer);
            }

            paper.dataset.erpPrevPosition = previousPosition;
        }

        function resetRomaneioPageNumbers() {
            const paper = document.querySelector('.viewer__paper');
            clearRomaneioPageLayers();
            if (paper && Object.prototype.hasOwnProperty.call(paper.dataset, 'erpPrevPosition')) {
                paper.style.position = paper.dataset.erpPrevPosition || '';
                delete paper.dataset.erpPrevPosition;
            }
        }

        window.addEventListener('beforeprint', prepareRomaneioPageNumbers);
        window.addEventListener('afterprint', resetRomaneioPageNumbers);

        window.addEventListener('load', () => {
            const paper = document.querySelector('.viewer__paper');
            if (! paper) {
                syncRomaneioPageLabels(1);
                return;
            }
            const pageHeightPx = Math.max(1, Math.floor(mmToPx(297 - 10 - 14)));
            syncRomaneioPageLabels(Math.max(1, Math.ceil(paper.scrollHeight / pageHeightPx)));
        });

        @if ($autoPrint)
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        @endif
    </script>
</body>
</html>
