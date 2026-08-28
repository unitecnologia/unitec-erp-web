@php
    use App\Support\Erp\Reports\ProductListagemReport;

    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = array_filter([
            'status' => ($filters['status'] ?? 'ativos') !== 'ativos' ? $filters['status'] : null,
            'ordenar' => ($filters['ordenar'] ?? 'descricao') !== 'descricao' ? $filters['ordenar'] : null,
            'estoque' => ($filters['estoque'] ?? 'todos') !== 'todos' ? $filters['estoque'] : null,
            'campo' => ($filters['campo'] ?? 'descricao') !== 'descricao' ? $filters['campo'] : null,
            'q' => filled($filters['q'] ?? null) ? $filters['q'] : null,
            'grupo' => filled($filters['grupo'] ?? null) ? $filters['grupo'] : null,
            'validade_de' => filled($filters['validade_de'] ?? null) ? $filters['validade_de'] : null,
            'validade_ate' => filled($filters['validade_ate'] ?? null) ? $filters['validade_ate'] : null,
            ...$extra,
        ], fn ($value): bool => filled($value) || is_array($value));

        if (isset($filters['cols']) && is_array($filters['cols'])) {
            foreach ($filters['cols'] as $column) {
                $params['cols'][] = $column;
            }
        }

        return $reportUrl . (str_contains($reportUrl, '?') ? '&' : '?') . http_build_query($params);
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Listagem de Produtos</title>
    <style>
        @page {
            margin: 10mm;
            size: A4 landscape;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #c7d5e8;
            font-family: Arial, Helvetica, sans-serif;
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
            font-size: 0.82rem;
            font-weight: 700;
            color: #0f2847;
        }

        .viewer__btn {
            min-width: 5.5rem;
            padding: 0.28rem 0.65rem;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a;
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

        .viewer__layout {
            display: grid;
            grid-template-columns: minmax(250px, 300px) minmax(0, 1fr);
            flex: 1;
            min-height: 0;
        }

        .viewer__filters {
            overflow: auto;
            padding: 0.55rem 0.6rem 0.7rem;
            background: #eef4fb;
            border-right: 1px solid #94a3b8;
        }

        .viewer__filters h2 {
            margin: 0 0 0.45rem;
            font-size: 0.78rem;
            color: #0f2847;
        }

        .viewer__filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem 0.4rem;
        }

        .viewer__field {
            margin-bottom: 0;
            min-width: 0;
        }

        .viewer__field--full {
            grid-column: 1 / -1;
        }

        .viewer__field label {
            display: block;
            margin-bottom: 0.1rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: #334155;
            line-height: 1.2;
        }

        .viewer__field select,
        .viewer__field input[type="text"],
        .viewer__field input[type="date"] {
            width: 100%;
            padding: 0.22rem 0.35rem;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            font-size: 0.72rem;
            line-height: 1.25;
            background: #fff;
        }

        .viewer__date-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem;
        }

        .viewer__date-range-item {
            display: grid;
            gap: 0.1rem;
            min-width: 0;
        }

        .viewer__date-range-item > span {
            font-size: 0.62rem;
            font-weight: 700;
            color: #475569;
            line-height: 1.2;
        }

        .erp-prod-date-wrap {
            position: relative;
            display: block;
            width: 100%;
        }

        .erp-prod-date-wrap input[type="date"] {
            padding-right: 1.5rem;
            min-height: 1.55rem;
            cursor: pointer;
            color-scheme: light;
        }

        .erp-prod-date-wrap input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            display: block;
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            cursor: pointer;
            z-index: 2;
        }

        .erp-prod-date-icon {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 1.5rem;
            background: transparent
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e5a9e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E")
                center / 0.85rem 0.85rem no-repeat;
            pointer-events: none;
            z-index: 1;
        }

        .viewer__columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.12rem 0.3rem;
            margin-top: 0.15rem;
        }

        .viewer__columns label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin: 0;
            font-size: 0.65rem;
            font-weight: 600;
            color: #334155;
            line-height: 1.2;
        }

        .viewer__columns input[type="checkbox"] {
            width: 0.85rem;
            height: 0.85rem;
            margin: 0;
            flex: 0 0 auto;
        }

        .viewer__actions {
            display: grid;
            gap: 0.35rem;
            margin-top: 0.5rem;
        }

        .viewer__actions .viewer__btn {
            width: 100%;
            min-width: 0;
            padding: 0.32rem 0.5rem;
            font-size: 0.72rem;
        }

        .viewer__actions .viewer__btn {
            width: 100%;
        }

        .viewer__canvas {
            overflow: auto;
            padding: 1rem;
        }

        .viewer__paper {
            width: min(297mm, 100%);
            margin: 0 auto;
        }

        @media print {
            body {
                background: #fff;
            }

            .viewer__toolbar,
            .viewer__filters {
                display: none;
            }

            .viewer__layout {
                display: block;
            }

            .viewer__canvas {
                padding: 0;
                overflow: visible;
            }

            .viewer__paper {
                width: 100%;
            }

            .prod-list-doc__frame {
                border: none;
                padding: 0;
            }
        }
    </style>
    @include('reports.partials.produtos-listagem-document-styles')
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
                    <div class="viewer__filters-grid">
                        <div class="viewer__field">
                            <label for="status">Situação</label>
                            <select id="status" name="status">
                                @foreach ($filterOptions['status'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? 'ativos') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="viewer__field">
                            <label for="ordenar">Ordenar por</label>
                            <select id="ordenar" name="ordenar">
                                @foreach ($filterOptions['ordenar'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['ordenar'] ?? 'descricao') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="viewer__field">
                            <label for="estoque">Estoque</label>
                            <select id="estoque" name="estoque">
                                @foreach ($filterOptions['estoque'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['estoque'] ?? 'todos') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="viewer__field">
                            <label for="grupo">Grupo</label>
                            <select id="grupo" name="grupo">
                                <option value="" @selected(blank($filters['grupo'] ?? ''))>Todos</option>
                                @foreach ($grupoOptions as $grupoOption)
                                    <option value="{{ $grupoOption }}" @selected(($filters['grupo'] ?? '') === $grupoOption)>{{ $grupoOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="viewer__field">
                            <label for="campo">Pesquisar em</label>
                            <select id="campo" name="campo">
                                @foreach ($filterOptions['campo'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['campo'] ?? 'descricao') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="viewer__field">
                            <label for="q">Texto</label>
                            <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Opcional">
                        </div>

                        <div class="viewer__field viewer__field--full">
                            <label>Validade (período)</label>
                            <div class="viewer__date-range">
                                <div class="viewer__date-range-item">
                                    <span>De</span>
                                    <div class="erp-prod-date-wrap">
                                        <input
                                            id="validade_de"
                                            type="date"
                                            name="validade_de"
                                            data-erp-native-date
                                            value="{{ $filters['validade_de'] ?? '' }}"
                                            onclick="try{this.showPicker()}catch(e){}"
                                        >
                                        <span class="erp-prod-date-icon" aria-hidden="true"></span>
                                    </div>
                                </div>
                                <div class="viewer__date-range-item">
                                    <span>Até</span>
                                    <div class="erp-prod-date-wrap">
                                        <input
                                            id="validade_ate"
                                            type="date"
                                            name="validade_ate"
                                            data-erp-native-date
                                            value="{{ $filters['validade_ate'] ?? '' }}"
                                            onclick="try{this.showPicker()}catch(e){}"
                                        >
                                        <span class="erp-prod-date-icon" aria-hidden="true"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="viewer__field viewer__field--full">
                            <label>Colunas</label>
                            <div class="viewer__columns">
                                @foreach ($filterOptions['columns'] as $value => $label)
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="cols[]"
                                            value="{{ $value }}"
                                            @checked(in_array($value, $filters['cols'] ?? [], true))
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar preview</button>
                    </div>
                </form>
            </aside>

            <div class="viewer__canvas">
                <div class="viewer__paper">
                    @include('reports.partials.produtos-listagem-document-body')
                </div>
            </div>
        </div>
    </div>

    <script>
        const closeUrl = @json($closeUrl);
        const reportFiltersForm = document.getElementById('report-filters-form');
        const pdfDownloadUrl = @json($buildUrl(['pdf' => 1]));
        const csvDownloadUrl = @json($buildUrl(['csv' => 1]));

        if (reportFiltersForm) {
            reportFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const params = new URLSearchParams();

                new FormData(reportFiltersForm).forEach(function (value, key) {
                    if (String(value).trim() !== '') {
                        params.append(key, value);
                    }
                });

                const query = params.toString();
                const url = query === ''
                    ? reportFiltersForm.action
                    : reportFiltersForm.action + '?' + query;

                window.location.replace(url);
            });
        }

        function savePdf() {
            window.open(pdfDownloadUrl, '_blank');
        }

        function saveCsv() {
            window.open(csvDownloadUrl, '_blank');
        }

        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-report-close' }, '*');
                return;
            }

            window.location.href = closeUrl;
        }

        @if ($autoPrint)
        window.addEventListener('load', function () {
            window.print();
        });
        @endif
    </script>
</body>
</html>
