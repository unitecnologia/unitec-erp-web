@php
    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = array_filter([
            'periodo' => ($filters['periodo'] ?? '1') !== '1' ? '0' : null,
            'de' => filled($filters['de'] ?? null) ? $filters['de'] : null,
            'ate' => filled($filters['ate'] ?? null) ? $filters['ate'] : null,
            'campo' => ($filters['campo'] ?? 'produto') !== 'produto' ? $filters['campo'] : null,
            'q' => filled($filters['q'] ?? null) ? $filters['q'] : null,
            ...$extra,
        ], fn ($value): bool => filled($value));

        return $reportUrl . '?' . http_build_query($params);
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Listagem de Ajustes de Estoque</title>
    <style>
        @page { margin: 10mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; padding: 0.35rem 0.5rem; background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%); border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%); color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__btn:hover { border-color: #1e5a9e; background: #ffffff; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__layout { display: grid; grid-template-columns: minmax(240px, 280px) minmax(0, 1fr); flex: 1; min-height: 0; }
        .viewer__filters { overflow: auto; padding: 0.85rem; background: #eef4fb; border-right: 1px solid #94a3b8; }
        .viewer__filters h2 { margin: 0 0 0.75rem; font-size: 0.9rem; color: #0f2847; }
        .viewer__field { margin-bottom: 0.65rem; }
        .viewer__field label { display: block; margin-bottom: 0.2rem; font-size: 0.72rem; font-weight: 700; color: #334155; }
        .viewer__field select, .viewer__field input[type="text"], .viewer__field input[type="date"] { width: 100%; padding: 0.35rem 0.45rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem; }
        .viewer__field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.45rem; }
        .viewer__check { display: flex; align-items: center; gap: 0.35rem; font-size: 0.74rem; font-weight: 600; }
        .viewer__actions { display: grid; gap: 0.45rem; margin-top: 0.85rem; }
        .viewer__actions .viewer__btn { width: 100%; }
        .viewer__canvas { overflow: auto; padding: 1rem; }
        .viewer__paper { width: min(210mm, 100%); margin: 0 auto; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar, .viewer__filters { display: none; }
            .viewer__layout { display: block; }
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
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            <button type="button" class="viewer__btn" onclick="saveCsv()">Exportar CSV</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__layout">
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="{{ $reportUrl }}" id="report-filters-form">
                    <div class="viewer__field">
                        <label class="viewer__check">
                            <input type="hidden" name="periodo" value="0">
                            <input type="checkbox" name="periodo" value="1" @checked(($filters['periodo'] ?? '1') === '1') onchange="this.previousElementSibling.disabled = this.checked">
                            Informar período
                        </label>
                    </div>

                    <div class="viewer__field-row">
                        <div class="viewer__field">
                            <label for="de">De</label>
                            <input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}">
                        </div>
                        <div class="viewer__field">
                            <label for="ate">Até</label>
                            <input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}">
                        </div>
                    </div>

                    <div class="viewer__field">
                        <label for="campo">Pesquisar em</label>
                        <select id="campo" name="campo">
                            @foreach ($filterOptions['campo'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['campo'] ?? 'produto') === $value)>{{ mb_strtoupper($label, 'UTF-8') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="viewer__field">
                        <label for="q">Texto</label>
                        <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}">
                    </div>

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar</button>
                    </div>
                </form>
            </aside>

            <div class="viewer__canvas">
                <div class="viewer__paper">
                    @include('reports.partials.ajustes-estoque-listagem-document-body')
                </div>
            </div>
        </div>
    </div>

    <script>
        function savePdf() {
            window.location.href = @json($buildUrl(['pdf' => 1]));
        }

        function saveCsv() {
            window.location.href = @json($buildUrl(['csv' => 1]));
        }

        function closePreview() {
            window.location.href = @json($closeUrl);
        }

        @if ($autoPrint ?? false)
            window.addEventListener('load', () => window.print());
        @endif
    </script>
</body>
</html>
