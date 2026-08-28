@php
    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = array_filter([
            'status' => ($filters['status'] ?? 'todos') !== 'todos' ? $filters['status'] : null,
            'tipo' => ($filters['tipo'] ?? 'todos') !== 'todos' ? $filters['tipo'] : null,
            'ordenar' => ($filters['ordenar'] ?? 'numero') !== 'numero' ? $filters['ordenar'] : null,
            'dir' => ($filters['dir'] ?? 'desc') !== 'desc' ? $filters['dir'] : null,
            'campo' => ($filters['campo'] ?? 'cliente') !== 'cliente' ? $filters['campo'] : null,
            'q' => filled($filters['q'] ?? null) ? $filters['q'] : null,
            'de' => filled($filters['de'] ?? null) ? $filters['de'] : null,
            'ate' => filled($filters['ate'] ?? null) ? $filters['ate'] : null,
            'hora_de' => filled($filters['hora_de'] ?? null) ? $filters['hora_de'] : null,
            'hora_ate' => filled($filters['hora_ate'] ?? null) ? $filters['hora_ate'] : null,
            ...$extra,
        ], fn ($value): bool => filled($value) || is_array($value));

        if (isset($filters['cols']) && is_array($filters['cols'])) {
            foreach ($filters['cols'] as $column) {
                $params['cols'][] = $column;
            }
        }

        return $reportUrl . '?' . http_build_query($params);
    };

    $campoAtual = $filters['campo'] ?? 'cliente';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Listagem de Vendas</title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; padding: 0.35rem 0.5rem; background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%); border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%); color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__btn:hover { border-color: #1e5a9e; background: #ffffff; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__layout { display: grid; grid-template-columns: minmax(260px, 300px) minmax(0, 1fr); flex: 1; min-height: 0; }
        .viewer__filters { overflow: auto; padding: 0.45rem 0.55rem 0.55rem; background: #eef4fb; border-right: 1px solid #94a3b8; }
        .viewer__filters h2 { margin: 0 0 0.4rem; font-size: 0.78rem; color: #0f2847; }
        .viewer__field { margin-bottom: 0.32rem; }
        .viewer__field label { display: block; margin-bottom: 0.1rem; font-size: 0.65rem; font-weight: 700; color: #334155; line-height: 1.15; }
        .viewer__field select, .viewer__field input[type="text"], .viewer__field input[type="date"], .viewer__field input[type="time"] { width: 100%; padding: 0.18rem 0.3rem; border: 1px solid #94a3b8; border-radius: 3px; font-size: 0.7rem; line-height: 1.2; height: 1.65rem; }
        .viewer__field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem; margin-bottom: 0.32rem; }
        .viewer__field-row > .viewer__field { margin-bottom: 0; }
        .viewer__columns { display: grid; grid-template-columns: 1fr 1fr; gap: 0.08rem 0.35rem; margin-top: 0.15rem; }
        .viewer__columns label { display: flex; align-items: center; gap: 0.22rem; font-size: 0.62rem; font-weight: 600; color: #334155; line-height: 1.2; margin: 0; }
        .viewer__columns input[type="checkbox"] { width: 0.78rem; height: 0.78rem; margin: 0; flex-shrink: 0; }
        .viewer__actions { display: grid; gap: 0.3rem; margin-top: 0.45rem; }
        .viewer__actions .viewer__btn { width: 100%; padding: 0.32rem 0.5rem; font-size: 0.72rem; min-width: 0; }
        .viewer__canvas { overflow: auto; padding: 1rem; }
        .viewer__paper { width: min(297mm, 100%); margin: 0 auto; }
        .viewer__search-panel[hidden] { display: none; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar, .viewer__filters { display: none; }
            .viewer__layout { display: block; }
            .viewer__canvas { padding: 0; overflow: visible; }
            .viewer__paper { width: 100%; }
            .venda-list-doc__frame { border: none; padding: 0; }
        }
    </style>
    @include('reports.partials.vendas-listagem-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            <button type="button" class="viewer__btn" onclick="saveCsv()">Exportar CSV</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__layout">
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="{{ $reportUrl }}" id="report-filters-form">
                    <div class="viewer__field-row">
                        <div class="viewer__field">
                            <label for="status">Situação</label>
                            <select id="status" name="status">
                                @foreach ($filterOptions['status'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? 'todos') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="viewer__field">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo">
                                @foreach ($filterOptions['tipo'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['tipo'] ?? 'todos') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="viewer__field-row">
                        <div class="viewer__field">
                            <label for="ordenar">Ordenar por</label>
                            <select id="ordenar" name="ordenar">
                                @foreach ($filterOptions['ordenar'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['ordenar'] ?? 'numero') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="viewer__field">
                            <label for="dir">Ordem</label>
                            <select id="dir" name="dir">
                                <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Decrescente</option>
                                <option value="asc" @selected(($filters['dir'] ?? 'desc') === 'asc')>Crescente</option>
                            </select>
                        </div>
                    </div>

                    <div class="viewer__field">
                        <label for="campo">Pesquisar em</label>
                        <select id="campo" name="campo">
                            @foreach ($filterOptions['campo'] as $value => $label)
                                <option value="{{ $value }}" @selected($campoAtual === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="search-text-panel" class="viewer__search-panel" @if ($campoAtual === 'data' || $campoAtual === 'hora') hidden @endif>
                        <div class="viewer__field">
                            <label for="q">Texto da pesquisa</label>
                            <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Opcional">
                        </div>
                    </div>

                    <div id="search-date-panel" class="viewer__search-panel" @if ($campoAtual !== 'data') hidden @endif>
                        <div class="viewer__field-row">
                            <div class="viewer__field">
                                <label for="de">Data de</label>
                                <input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}">
                            </div>
                            <div class="viewer__field">
                                <label for="ate">Data até</label>
                                <input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    <div id="search-time-panel" class="viewer__search-panel" @if ($campoAtual !== 'data' && $campoAtual !== 'hora') hidden @endif>
                        <div class="viewer__field-row">
                            <div class="viewer__field">
                                <label for="hora_de">Hora de</label>
                                <input id="hora_de" type="time" name="hora_de" step="1" value="{{ $filters['hora_de'] ?? '' }}">
                            </div>
                            <div class="viewer__field">
                                <label for="hora_ate">Hora até</label>
                                <input id="hora_ate" type="time" name="hora_ate" step="1" value="{{ $filters['hora_ate'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="viewer__field">
                        <label>Colunas</label>
                        <div class="viewer__columns">
                            @foreach ($filterOptions['columns'] as $value => $label)
                                <label>
                                    <input type="checkbox" name="cols[]" value="{{ $value }}" @checked(in_array($value, $filters['cols'] ?? [], true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar preview</button>
                    </div>
                </form>
            </aside>

            <div class="viewer__canvas">
                <div class="viewer__paper">
                    @include('reports.partials.vendas-listagem-document-body')
                </div>
            </div>
        </div>
    </div>

    <script>
        const closeUrl = @json($closeUrl);
        const reportFiltersForm = document.getElementById('report-filters-form');
        const campoSelect = document.getElementById('campo');
        const pdfDownloadUrl = @json($buildUrl(['pdf' => 1]));
        const csvDownloadUrl = @json($buildUrl(['csv' => 1]));

        function toggleSearchPanels() {
            const campo = campoSelect ? campoSelect.value : 'cliente';
            const textPanel = document.getElementById('search-text-panel');
            const datePanel = document.getElementById('search-date-panel');
            const timePanel = document.getElementById('search-time-panel');

            if (textPanel) textPanel.hidden = campo === 'data' || campo === 'hora';
            if (datePanel) datePanel.hidden = campo !== 'data';
            if (timePanel) timePanel.hidden = campo !== 'data' && campo !== 'hora';
        }

        if (campoSelect) {
            campoSelect.addEventListener('change', toggleSearchPanels);
            toggleSearchPanels();
        }

        if (reportFiltersForm) {
            reportFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const params = new URLSearchParams();
                const campo = campoSelect ? campoSelect.value : 'cliente';

                new FormData(reportFiltersForm).forEach(function (value, key) {
                    if (String(value).trim() === '') return;
                    if (campo !== 'data' && (key === 'de' || key === 'ate')) return;
                    // Hora pode acompanhar a data (periodo do dia) ou ser o unico criterio.
                    if (campo !== 'data' && campo !== 'hora' && (key === 'hora_de' || key === 'hora_ate')) return;
                    if (campo === 'data' && key === 'q') return;
                    if (campo === 'hora' && key === 'q') return;
                    params.append(key, value);
                });

                const query = params.toString();
                const url = query === '' ? reportFiltersForm.action : reportFiltersForm.action + '?' + query;
                window.location.replace(url);
            });
        }

        function savePdf() { window.open(pdfDownloadUrl, '_blank'); }
        function saveCsv() { window.open(csvDownloadUrl, '_blank'); }

        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-report-close' }, '*');
                return;
            }
            window.location.href = closeUrl;
        }

        @if ($autoPrint)
        window.addEventListener('load', function () { window.print(); });
        @endif
    </script>
</body>
</html>
