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
        @page { margin: 10mm; size: A4 {{ ($paperOrientation ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape' }}; }
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
        .viewer__layout--no-filters { grid-template-columns: minmax(0, 1fr); }
        .viewer__filters { overflow: auto; padding: 0.85rem; background: #eef4fb; border-right: 1px solid #94a3b8; }
        .viewer__filters h2 { margin: 0 0 0.75rem; font-size: 0.9rem; color: #0f2847; }
        .viewer__field { margin-bottom: 0.65rem; }
        .viewer__field label { display: block; margin-bottom: 0.2rem; font-size: 0.72rem; font-weight: 700; color: #334155; }
        .viewer__field select,
        .viewer__field input[type="text"],
        .viewer__field input[type="date"] {
            width: 100%; padding: 0.35rem 0.45rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem;
        }
        .viewer__lookup { position: relative; }
        .viewer__lookup-list {
            position: absolute; z-index: 4; left: 0; right: 0; max-height: 11rem; overflow: auto;
            margin-top: 2px; background: #fff; border: 1px solid #94a3b8; border-radius: 4px;
        }
        .viewer__lookup-item {
            display: block; width: 100%; margin: 0; padding: 0.28rem 0.4rem; border: 0;
            background: #fff; color: #0f172a; font-size: 0.75rem; text-align: left; cursor: pointer;
        }
        .viewer__lookup-item:hover,
        .viewer__lookup-item--active { background: #dbeafe; }
        .viewer__columns { display: grid; gap: 0.25rem; margin-top: 0.35rem; }
        .viewer__columns label {
            display: flex; align-items: center; gap: 0.35rem;
            font-size: 0.72rem; font-weight: 600; color: #334155;
        }
        .viewer__section {
            margin: 0.85rem 0 0.45rem; padding-top: 0.45rem;
            border-top: 1px solid #cbd5e1;
            font-size: 0.78rem; font-weight: 800; color: #0f2847; text-transform: uppercase;
        }
        .viewer__section:first-child { border-top: 0; margin-top: 0; padding-top: 0; }
        .viewer__static {
            padding: 0.35rem 0.45rem; border: 1px solid #cbd5e1; border-radius: 4px;
            background: #f8fafc; font-size: 0.78rem; font-weight: 600; color: #0f172a;
        }
        .viewer__check {
            display: flex; align-items: center; gap: 0.4rem;
            font-size: 0.74rem; font-weight: 600; color: #334155;
        }
        .viewer__check input { width: auto; }
        .viewer__pending {
            background: #fff; border: 1px dashed #94a3b8; border-radius: 8px;
            padding: 1.5rem 1.25rem; max-width: 40rem; margin: 1.5rem auto;
            color: #334155; font-size: 0.92rem; line-height: 1.45; text-align: center;
        }
        .viewer__actions { display: grid; gap: 0.45rem; margin-top: 0.85rem; }
        .viewer__actions .viewer__btn { width: 100%; }
        .viewer__btn--primary {
            border-color: #1e5a9e;
            background: linear-gradient(180deg, #dbeafe 0%, #93c5fd 100%);
            color: #0f2847;
        }
        .viewer__canvas { overflow: auto; padding: 1rem; display: flex; flex-direction: column; min-height: 0; }
        .viewer__paper { width: min({{ ($paperOrientation ?? 'landscape') === 'portrait' ? '210mm' : '297mm' }}, 100%); margin: 0 auto; }
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
            .tabular-doc__detail-row { display: none !important; }
            .tabular-doc__qtd-toggle {
                color: inherit !important;
                font-weight: inherit !important;
                text-decoration: none !important;
            }
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
            @if (! empty($filtersBackUrl))
                <button type="button" class="viewer__btn" onclick="backToFilters()">Voltar aos Filtros</button>
            @endif
            <button type="button" class="viewer__btn" onclick="printReport()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            @unless (filled($pdvResumoUrl) || ((! empty($filterFields)) && collect($filterFields)->contains(fn ($f) => ($f['key'] ?? '') === 'caixa') && empty($hideFilterSidebar)))
                <button type="button" class="viewer__btn" onclick="saveCsv()">Exportar CSV</button>
            @endunless
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        {{-- Form oculto: preserva filtros para PDF/CSV quando o viewer não tem sidebar --}}
        @if (! empty($hideFilterSidebar))
            <form id="report-filters-form" method="get" action="{{ $reportUrl }}" hidden>
                @foreach ($filters as $fkey => $fval)
                    @if ($fkey === 'cols' && is_array($fval))
                        @foreach ($fval as $col)
                            <input type="hidden" name="cols[]" value="{{ $col }}">
                        @endforeach
                    @elseif (is_scalar($fval) || $fval === null)
                        <input type="hidden" name="{{ $fkey }}" value="{{ $fval }}">
                    @endif
                @endforeach
                <input type="hidden" name="gerar" id="filter-gerar" value="1">
            </form>
        @endif

        <div class="viewer__layout{{ ! empty($hideFilterSidebar) ? ' viewer__layout--no-filters' : '' }}">
            @unless (! empty($hideFilterSidebar))
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="{{ $reportUrl }}" id="report-filters-form">
                    @foreach ($filterFields as $field)
                        @php
                            $key = $field['key'];
                            $type = $field['type'] ?? 'text';
                            $value = $filters[$key] ?? ($type === 'columns' ? ($filters['cols'] ?? []) : ($field['value'] ?? ''));
                        @endphp

                        @if ($type === 'section')
                            <div class="viewer__section">{{ $field['label'] }}</div>
                        @else
                        <div class="viewer__field">
                            @if ($type === 'checkbox')
                                <label class="viewer__check" for="filter-{{ $key }}">
                                    <input
                                        id="filter-{{ $key }}"
                                        type="checkbox"
                                        name="{{ $key }}"
                                        value="1"
                                        @checked(filled($value) && $value !== '0' && $value !== 0 && $value !== false)
                                    >
                                    <span>{{ $field['label'] }}</span>
                                </label>
                            @elseif ($type === 'static')
                                <label>{{ $field['label'] }}</label>
                                <div class="viewer__static">{{ $value }}</div>
                            @else
                                <label for="filter-{{ $key }}">{{ $field['label'] }}</label>

                                @if ($type === 'lookup')
                                    @php
                                        $lookupOptions = [];
                                        $lookupLabel = '';
                                        foreach (($field['options'] ?? []) as $optionValue => $optionLabel) {
                                            $lookupOptions[] = ['v' => (string) $optionValue, 'l' => (string) $optionLabel];
                                            if ((string) $value === (string) $optionValue) {
                                                $lookupLabel = (string) $optionLabel;
                                            }
                                        }
                                    @endphp
                                    <div class="viewer__lookup" data-erp-lookup>
                                        <input
                                            id="filter-{{ $key }}-q"
                                            type="text"
                                            value="{{ $lookupLabel }}"
                                            placeholder="{{ $field['placeholder'] ?? 'Todos' }}"
                                            autocomplete="off"
                                            spellcheck="false"
                                        >
                                        <input type="hidden" id="filter-{{ $key }}" name="{{ $key }}" value="{{ $value }}">
                                        <div class="viewer__lookup-list" hidden></div>
                                        <script type="application/json" data-erp-lookup-options>@json($lookupOptions)</script>
                                    </div>
                                @elseif ($type === 'select')
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
                                    <input
                                        id="filter-{{ $key }}"
                                        type="text"
                                        name="{{ $key }}"
                                        value="{{ $value }}"
                                        placeholder="{{ $field['placeholder'] ?? 'Opcional' }}"
                                    >
                                @endif
                            @endif
                        </div>
                        @endif
                    @endforeach

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar preview</button>
                    </div>
                </form>
            </aside>
            @endunless

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

        @if (collect($filterFields ?? [])->contains(fn ($field): bool => ($field['type'] ?? '') === 'lookup'))
        document.querySelectorAll('[data-erp-lookup]').forEach(function (root) {
            var input = root.querySelector('input[type="text"]');
            var hidden = root.querySelector('input[type="hidden"]');
            var list = root.querySelector('.viewer__lookup-list');
            var dataEl = root.querySelector('[data-erp-lookup-options]');
            if (!input || !hidden || !list || !dataEl) {
                return;
            }
            var options = [];
            try {
                options = JSON.parse(dataEl.textContent || '[]');
            } catch (e) {
                options = [];
            }
            var active = -1;
            var selectedLabel = input.value;

            function fold(value) {
                return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }

            function choose(value, label) {
                hidden.value = value;
                input.value = label;
                selectedLabel = label;
                list.hidden = true;
                list.textContent = '';
                active = -1;
            }

            function syncHidden() {
                var text = input.value.trim();
                if (text === '') {
                    hidden.value = '';
                    selectedLabel = '';
                    return;
                }
                if (hidden.value !== '' && fold(text) === fold(selectedLabel)) {
                    return;
                }
                var query = fold(text);
                for (var i = 0; i < options.length; i++) {
                    if (fold(options[i].l) === query) {
                        choose(options[i].v, options[i].l);
                        return;
                    }
                }
                hidden.value = '';
            }

            function render(term) {
                var query = fold(term);
                var matches = [];
                for (var i = 0; i < options.length; i++) {
                    if (query === '' || fold(options[i].l).indexOf(query) !== -1) {
                        matches.push(options[i]);
                        if (matches.length >= 40) {
                            break;
                        }
                    }
                }
                list.textContent = '';
                var todos = document.createElement('button');
                todos.type = 'button';
                todos.className = 'viewer__lookup-item';
                todos.textContent = input.getAttribute('placeholder') || 'Todos';
                todos.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose('', '');
                });
                list.appendChild(todos);
                matches.forEach(function (opt) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'viewer__lookup-item';
                    button.textContent = opt.l;
                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        choose(opt.v, opt.l);
                    });
                    list.appendChild(button);
                });
                list.hidden = false;
                active = -1;
            }

            input.addEventListener('focus', function () { render(input.value); });
            input.addEventListener('input', function () {
                if (fold(input.value) !== fold(selectedLabel)) {
                    hidden.value = '';
                }
                render(input.value);
            });
            input.addEventListener('blur', function () {
                syncHidden();
                if (hidden.value === '' && input.value.trim() !== '') {
                    input.value = '';
                }
                list.hidden = true;
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    list.hidden = true;
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (list.hidden) {
                        render(input.value);
                    }
                    var items = list.querySelectorAll('.viewer__lookup-item');
                    if (!items.length) {
                        return;
                    }
                    active += event.key === 'ArrowDown' ? 1 : -1;
                    if (active < 0) {
                        active = items.length - 1;
                    }
                    if (active >= items.length) {
                        active = 0;
                    }
                    items.forEach(function (el, index) {
                        el.classList.toggle('viewer__lookup-item--active', index === active);
                    });
                    return;
                }
                if (event.key === 'Enter' && !list.hidden) {
                    var items = list.querySelectorAll('.viewer__lookup-item');
                    if (active >= 0 && items[active]) {
                        event.preventDefault();
                        items[active].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                    }
                }
            });

            var form = root.closest('form');
            if (form) {
                form.addEventListener('submit', syncHidden, true);
            }
        });
        @endif

        if (reportFiltersForm) {
            reportFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();
                submitReportFilters();
            });

            // Resumo Caixa apenas (tem #filter-data). Não aplicar a outros relatórios com #filter-caixa.
            const isResumoCaixaForm = !!document.getElementById('filter-data')
                && !!document.getElementById('filter-usuario')
                && !!document.getElementById('filter-caixa');
            if (isResumoCaixaForm) {
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
        }

        function printReport() {
            if (pdvResumoUrl) {
                window.open(pdvResumoUrl, '_blank');
                return;
            }
            if (document.getElementById('filter-data') && document.getElementById('filter-caixa') && !pdvResumoUrl) {
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
            if (document.getElementById('filter-data') && document.getElementById('filter-caixa') && !pdvResumoUrl) {
                window.alert('Selecione o Caixa (abertura/fechamento) para imprimir o resumo.');
                return;
            }
            window.open(pdfDownloadUrl, '_blank');
        }
        function saveCsv() { window.open(csvDownloadUrl, '_blank'); }

        function backToFilters() {
            const url = @json($filtersBackUrl ?? null);
            if (url) {
                window.location.href = url;
            }
        }

        function closePreview() {
            const filtersUrl = @json($filtersBackUrl ?? null);
            if (filtersUrl) {
                window.location.href = filtersUrl;
                return;
            }
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
        <script src="{{ asset('js/erp-report-charts.js') }}?v=7"></script>
        <script>
            window.ErpReportCharts.init({
                chartDataUrl: @json($chartDataUrl),
                chartJsUrl: @json(asset('js/vendor/chart.umd.min.js')),
                chartType: @json(($chartConfig['type'] ?? null) ?: 'line'),
                defaultGranularity: @json(($chartConfig['default_granularity'] ?? null) ?: 'month'),
            });
        </script>
    @endif
    @if (! empty($supportsRowExpand) && filled($detailsUrl ?? null))
        @php
            $expandFilters = collect($filters ?? [])->except(['pdv_resumo_url'])->all();
            $expandMode = (string) (($reportMeta['row_expand_mode'] ?? '') ?: '');
            if ($expandMode === '' && ! empty($rows[0]['_expand']['mode'])) {
                $expandMode = (string) $rows[0]['_expand']['mode'];
            }
        @endphp
        @if ($expandMode === 'produtos')
            <script src="{{ asset('js/erp-report-venda-produtos-expand.js') }}?v=1"></script>
            <script>
                window.ErpReportVendaProdutosExpand.init({
                    detailsUrl: @json($detailsUrl),
                    filters: @json($expandFilters),
                });
            </script>
        @else
            <script src="{{ asset('js/erp-report-vendedor-expand.js') }}?v=1"></script>
            <script>
                window.ErpReportVendedorExpand.init({
                    detailsUrl: @json($detailsUrl),
                    filters: @json($expandFilters),
                });
            </script>
        @endif
    @endif
</body>
</html>
