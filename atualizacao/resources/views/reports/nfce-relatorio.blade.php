@php
    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = array_filter([
            'status' => ($filters['status'] ?? '') !== '' ? $filters['status'] : null,
            'de' => filled($filters['de'] ?? null) ? $filters['de'] : null,
            'ate' => filled($filters['ate'] ?? null) ? $filters['ate'] : null,
            'chave' => filled($filters['chave'] ?? null) ? $filters['chave'] : null,
            'campo' => ($filters['campo'] ?? 'serie') !== 'serie' ? $filters['campo'] : null,
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
    <title>Visualizar — Relatório NFC-e</title>
    <style>
        @page { margin: 8mm; size: A4 landscape; }
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
        .viewer__field select, .viewer__field input[type="date"] { width: 100%; padding: 0.35rem 0.45rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem; }
        .viewer__field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.45rem; }
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
            .nfce-relatorio-doc__frame { border: none; padding: 0; margin-bottom: 0; }
        }
    </style>
    @include('reports.partials.nfce-relatorio-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Relatório NFC-e</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__layout">
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="{{ $reportUrl }}" id="report-filters-form">
                    <div class="viewer__field">
                        <label for="status">Situação</label>
                        <select id="status" name="status">
                            @foreach ($filterOptions['status'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? 'transmitidos') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="viewer__field-row">
                        <div class="viewer__field">
                            <label for="de">Período de</label>
                            <input id="de" type="date" name="de" value="{{ $filters['de'] ?? '' }}">
                        </div>
                        <div class="viewer__field">
                            <label for="ate">até</label>
                            <input id="ate" type="date" name="ate" value="{{ $filters['ate'] ?? '' }}">
                        </div>
                    </div>

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar</button>
                    </div>
                </form>
            </aside>

            <main class="viewer__canvas">
                <div class="viewer__paper">
                    @include('reports.partials.nfce-relatorio-document-body')
                </div>
            </main>
        </div>
    </div>

    <script>
        const pdfDownloadUrl = @json($buildUrl(['pdf' => 1]));
        const closeUrl = @json($closeUrl);
        const autoPrint = @json($autoPrint);

        function savePdf() {
            window.location.href = pdfDownloadUrl;
        }

        function closePreview() {
            window.location.href = closeUrl;
        }

        if (autoPrint) {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
