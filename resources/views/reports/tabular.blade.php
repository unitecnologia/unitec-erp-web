@php
    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = [];

        foreach ($filters as $key => $value) {
            if ($key === 'pdv_resumo_url') {
                continue;
            }

            if ($key === 'cols' && is_array($value)) {
                foreach ($value as $column) {
                    $params['cols'][] = $column;
                }
                continue;
            }

            if (filled($value) || $value === '0' || $value === 0) {
                $params[$key] = $value;
            }
        }

        foreach ($extra as $key => $value) {
            $params[$key] = $value;
        }

        $params = array_filter(
            $params,
            fn ($value): bool => filled($value) || is_array($value) || $value === '0' || $value === 0,
        );

        return $reportUrl . '?' . http_build_query($params);
    };

    $pdvResumoUrl = $filters['pdv_resumo_url'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — {{ $reportTitle }}</title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar {
            display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;
            padding: 0.35rem 0.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
            border-bottom: 1px solid #94a3b8;
        }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn {
            min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer;
        }
        .viewer__btn:hover { border-color: #1e5a9e; background: #ffffff; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__btn--active {
            border-color: #1e5a9e; background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%); color: #0f2847;
        }
        .viewer__mode { display: inline-flex; gap: 0.2rem; margin-right: 0.35rem; }
        .viewer__chart-panel {
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 0.65rem 0.85rem 0.75rem; min-height: 0;
        }
        .viewer__chart-toolbar {
            display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end;
            margin-bottom: 0.5rem;
        }
        .viewer__chart-field label {
            display: block; margin-bottom: 0.15rem; font-size: 0.72rem; font-weight: 700; color: #334155;
        }
        .viewer__chart-field select {
            min-width: 11rem; padding: 0.3rem 0.4rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem;
        }
        .viewer__chart-check {
            display: flex; align-items: center; gap: 0.35rem;
            font-size: 0.75rem; font-weight: 600; color: #334155; padding-bottom: 0.2rem;
        }
        .viewer__chart-cards {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.45rem;
            margin-bottom: 0.45rem;
        }
        .viewer__chart-card {
            border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;
            padding: 0.4rem 0.55rem; min-width: 0;
        }
        .viewer__chart-card__title {
            display: block; font-size: 0.68rem; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.02em;
        }
        .viewer__chart-card__value {
            display: block; margin-top: 0.1rem; font-size: 0.92rem; font-weight: 700; color: #0f2847;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .viewer__chart-card__hint {
            display: block; margin-top: 0.05rem; font-size: 0.68rem; font-weight: 600; color: #64748b;
        }
        .viewer__chart-meta {
            font-size: 0.72rem; color: #64748b; margin: 0 0 0.35rem;
        }
        .viewer__chart-canvas-wrap { position: relative; height: 320px; width: 100%; }
        .viewer__chart-status { font-size: 0.78rem; color: #64748b; min-height: 0; margin-bottom: 0.25rem; }
        @media (max-width: 720px) {
            .viewer__chart-cards { grid-template-columns: 1fr; }
        }
        .viewer__layout { display: grid; grid-template-columns: minmax(240px, 280px) minmax(0, 1fr); flex: 1; min-height: 0; }
        .viewer__filters { overflow: auto; padding: 0.85rem; background: #eef4fb; border-right: 1px solid #94a3b8; }
        .viewer__filters h2 { margin: 0 0 0.75rem; font-size: 0.9rem; color: #0f2847; }
        .viewer__field { margin-bottom: 0.65rem; }
        .viewer__field label { display: block; margin-bottom: 0.2rem; font-size: 0.72rem; font-weight: 700; color: #334155; }
        .viewer__field select,
        .viewer__field input[type="text"],
        .viewer__field input[type="date"] {
            width: 100%; padding: 0.35rem 0.45rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem;
        }
        .viewer__columns { display: grid; gap: 0.25rem; margin-top: 0.35rem; }
        .viewer__columns label {
            display: flex; align-items: center; gap: 0.35rem;
            font-size: 0.72rem; font-weight: 600; color: #334155;
        }
        .viewer__actions { display: grid; gap: 0.45rem; margin-top: 0.85rem; }
        .viewer__actions .viewer__btn { width: 100%; }
        .viewer__canvas { overflow: auto; padding: 1rem; display: flex; flex-direction: column; min-height: 0; }
        .viewer__paper { width: min(297mm, 100%); margin: 0 auto; }
        .viewer__pdv-resumo {
            flex: 1; min-height: 0; display: flex; flex-direction: column;
            background: #fff; border: 1px solid #94a3b8; border-radius: 6px; overflow: hidden;
        }
        .viewer__pdv-resumo iframe {
            flex: 1; width: 100%; min-height: calc(100vh - 4.5rem); border: 0; background: #fff;
        }
        .viewer__pdv-empty {
            background: #fff; border: 1px solid #94a3b8; border-radius: 6px;
            padding: 1.25rem 1.5rem; max-width: 36rem; margin: 1rem auto;
            color: #334155; font-size: 0.9rem; line-height: 1.45;
        }
        @media print {
            body { background: #fff; }
            .viewer__toolbar, .viewer__filters, #erp-report-chart-view { display: none !important; }
            #erp-report-table-view { display: block !important; }
            .viewer__layout { display: block; }
            .viewer__canvas { padding: 0; overflow: visible; }
            .viewer__paper, #erp-report-table-view { width: 100%; }
            .tabular-doc__frame { border: none; padding: 0; }
            .viewer__pdv-resumo { border: none; }
            .viewer__pdv-resumo iframe { min-height: 100vh; }
        }
    </style>
    @include('reports.partials.tabular-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar</span>
            @if (! empty($supportsChart))
                <span class="viewer__mode">
                    <button type="button" class="viewer__btn viewer__btn--active" id="erp-report-mode-table">Tabela</button>
                    <button type="button" class="viewer__btn" id="erp-report-mode-chart">Gráfico</button>
                </span>
            @endif
            <button type="button" class="viewer__btn" onclick="printReport()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            @unless (filled($pdvResumoUrl) || collect($filterFields)->contains(fn ($f) => ($f['key'] ?? '') === 'caixa'))
                <button type="button" class="viewer__btn" onclick="saveCsv()">Exportar CSV</button>
            @endunless
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__layout">
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="{{ $reportUrl }}" id="report-filters-form">
                    @foreach ($filterFields as $field)
                        @php
                            $key = $field['key'];
                            $type = $field['type'] ?? 'text';
                            $value = $filters[$key] ?? ($type === 'columns' ? ($filters['cols'] ?? []) : '');
                        @endphp

                        <div class="viewer__field">
                            <label for="filter-{{ $key }}">{{ $field['label'] }}</label>

                            @if ($type === 'select')
                                <select id="filter-{{ $key }}" name="{{ $key }}">
                                    @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'date')
                                <input id="filter-{{ $key }}" type="date" name="{{ $key }}" value="{{ $value }}">
                            @elseif ($type === 'columns')
                                <div class="viewer__columns">
                                    @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="cols[]"
                                                value="{{ $optionValue }}"
                                                @checked(in_array($optionValue, is_array($value) ? $value : ($filters['cols'] ?? []), true))
                                            >
                                            <span>{{ $optionLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input id="filter-{{ $key }}" type="text" name="{{ $key }}" value="{{ $value }}" placeholder="Opcional">
                            @endif
                        </div>
                    @endforeach

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar preview</button>
                    </div>
                </form>
            </aside>

            <div class="viewer__canvas">
                @php
                    $isResumoCaixaPdv = collect($filterFields)->contains(fn ($f) => ($f['key'] ?? '') === 'caixa');
                @endphp

                @if (filled($pdvResumoUrl))
                    <div id="erp-report-pdv-resumo-view" class="viewer__pdv-resumo">
                        <iframe
                            src="{{ $pdvResumoUrl }}"
                            title="Resumo Caixa"
                            loading="eager"
                        ></iframe>
                    </div>
                @elseif ($isResumoCaixaPdv)
                    <div class="viewer__pdv-empty" id="erp-report-pdv-empty">
                        {{ $summary[0] ?? 'Selecione Data, Usuário e Caixa (abertura/fechamento) para ver o resumo.' }}
                    </div>
                @else
                    <div id="erp-report-table-view" class="viewer__paper">
                        @include('reports.partials.tabular-document-body')
                    </div>
                @endif

                @if (! empty($supportsChart))
                    @php
                        $chartCfg = $chartConfig ?? [];
                        $chartEmpresas = $chartCfg['empresas'] ?? [];
                        $showEmpresaSelect = ! empty($chartCfg['show_empresa_select']);
                    @endphp
                    <div id="erp-report-chart-view" class="viewer__chart-panel" hidden>
                        <div class="viewer__chart-toolbar">
                            @if ($showEmpresaSelect)
                                <div class="viewer__chart-field">
                                    <label for="erp-report-chart-empresas">Empresa</label>
                                    <select id="erp-report-chart-empresas">
                                        <option value="todas">Todas as Empresas</option>
                                        @foreach ($chartEmpresas as $emp)
                                            <option value="{{ $emp['id'] }}">{{ $emp['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (! empty($chartCfg['supports_granularity']))
                                <div class="viewer__chart-field">
                                    <label for="erp-report-chart-granularity">Agrupar por</label>
                                    <select id="erp-report-chart-granularity">
                                        <option value="month">Mês</option>
                                        <option value="day">Dia</option>
                                    </select>
                                </div>
                            @endif
                            @if (! empty($chartCfg['supports_yoy']))
                                <label class="viewer__chart-check">
                                    <input type="checkbox" id="erp-report-chart-yoy" value="1">
                                    <span>Comparar ano atual × anterior</span>
                                </label>
                            @endif
                            <button type="button" class="viewer__btn" id="erp-report-chart-refresh">Atualizar gráfico</button>
                        </div>
                        <div class="viewer__chart-cards" id="erp-report-chart-cards" hidden>
                            <div class="viewer__chart-card" id="erp-report-chart-card-total">
                                <span class="viewer__chart-card__title" data-card-title>Total vendido</span>
                                <span class="viewer__chart-card__value" data-card-value>—</span>
                                <span class="viewer__chart-card__hint" data-card-hint hidden></span>
                            </div>
                            <div class="viewer__chart-card" id="erp-report-chart-card-best">
                                <span class="viewer__chart-card__title" data-card-title>Melhor período</span>
                                <span class="viewer__chart-card__value" data-card-value>—</span>
                                <span class="viewer__chart-card__hint" data-card-hint hidden></span>
                            </div>
                            <div class="viewer__chart-card" id="erp-report-chart-card-avg">
                                <span class="viewer__chart-card__title" data-card-title>Média</span>
                                <span class="viewer__chart-card__value" data-card-value>—</span>
                                <span class="viewer__chart-card__hint" data-card-hint hidden></span>
                            </div>
                        </div>
                        <div class="viewer__chart-meta" id="erp-report-chart-meta"></div>
                        <div class="viewer__chart-status" id="erp-report-chart-status"></div>
                        <div class="viewer__chart-canvas-wrap">
                            <canvas id="erp-report-chart-canvas" aria-label="Gráfico do relatório"></canvas>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        const closeUrl = @json($closeUrl);
        const reportFiltersForm = document.getElementById('report-filters-form');
        const pdfDownloadUrl = @json($buildUrl(['pdf' => 1]));
        const csvDownloadUrl = @json($buildUrl(['csv' => 1]));
        const pdvResumoUrl = @json($pdvResumoUrl);

        function submitReportFilters() {
            if (!reportFiltersForm) {
                return;
            }
            const params = new URLSearchParams();
            new FormData(reportFiltersForm).forEach(function (value, key) {
                if (String(value).trim() !== '') {
                    params.append(key, value);
                }
            });
            const query = params.toString();
            window.location.replace(query === '' ? reportFiltersForm.action : reportFiltersForm.action + '?' + query);
        }

        if (reportFiltersForm) {
            reportFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();
                submitReportFilters();
            });

            // Resumo Caixa: Data/Usuário remontam o combo Caixa; Caixa carrega o cupom.
            ['filter-data', 'filter-usuario', 'filter-caixa'].forEach(function (id) {
                const el = document.getElementById(id);
                if (!el) {
                    return;
                }
                el.addEventListener('change', function () {
                    if (id !== 'filter-caixa') {
                        const caixa = document.getElementById('filter-caixa');
                        if (caixa) {
                            caixa.value = '';
                        }
                    }
                    submitReportFilters();
                });
            });
        }

        function printReport() {
            if (pdvResumoUrl) {
                window.open(pdvResumoUrl, '_blank');
                return;
            }
            if (document.getElementById('filter-caixa')) {
                window.alert('Selecione o Caixa (abertura/fechamento) para imprimir o resumo.');
                return;
            }
            window.print();
        }

        function savePdf() {
            if (pdvResumoUrl) {
                window.open(pdvResumoUrl, '_blank');
                return;
            }
            if (document.getElementById('filter-caixa')) {
                window.alert('Selecione o Caixa (abertura/fechamento) para imprimir o resumo.');
                return;
            }
            window.open(pdfDownloadUrl, '_blank');
        }
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
    @if (! empty($supportsChart))
        <script src="{{ asset('js/erp-report-charts.js') }}?v=6"></script>
        <script>
            window.ErpReportCharts.init({
                chartDataUrl: @json($chartDataUrl),
                chartJsUrl: @json(asset('js/vendor/chart.umd.min.js')),
                chartType: @json(($chartConfig['type'] ?? null) ?: 'line'),
                defaultGranularity: @json(($chartConfig['default_granularity'] ?? null) ?: 'month'),
            });
        </script>
    @endif
</body>
</html>
