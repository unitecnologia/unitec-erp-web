<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — Férias</title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; padding: 0.35rem 0.5rem; background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%); border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%); color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__canvas { overflow: auto; padding: 1rem; flex: 1; }
        .viewer__paper { width: min(297mm, 100%); margin: 0 auto; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar { display: none; }
            .viewer__canvas { padding: 0; overflow: visible; }
            .viewer__paper { width: 100%; }
        }
    </style>
    @include('reports.partials.pessoas-listagem-document-styles')
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar — Férias</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="window.location.href=@js($closeUrl)">Fechar</button>
        </div>

        <div class="viewer__canvas">
            <div class="viewer__paper">
                <div class="pessoa-list-doc">
                    <div class="pessoa-list-doc__frame">
                        <div class="pessoa-list-doc__header">
                            <div class="pessoa-list-doc__logo-cell">
                                @if ($logoDataUri)
                                    <img class="pessoa-list-doc__logo" src="{{ $logoDataUri }}" alt="">
                                @elseif ($logoUrl)
                                    <img class="pessoa-list-doc__logo" src="{{ $logoUrl }}" alt="">
                                @endif
                            </div>
                            <div class="pessoa-list-doc__company-cell">
                                <div class="pessoa-list-doc__company-name">{{ mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->fantasia ?: $empresa?->nome ?: 'EMPRESA'), 'UTF-8') }}</div>
                                @if (filled($empresaEndereco))
                                    <div class="pessoa-list-doc__company-meta">{{ $empresaEndereco }}</div>
                                @endif
                            </div>
                        </div>

                        <hr class="pessoa-list-doc__rule">

                        <div class="pessoa-list-doc__title">{{ $reportTitle }}</div>
                        <div class="pessoa-list-doc__filters">
                            <span>| SITUAÇÃO: {{ mb_strtoupper($statusLabel, 'UTF-8') }}</span>
                            <span>| ALERTA: {{ mb_strtoupper($alertaLabel, 'UTF-8') }}</span>
                            @if ($searchLabel)
                                <span>| FILTRO: {{ $searchLabel }}</span>
                            @endif
                            <span>| REGISTROS: {{ $gozos->count() }}{{ $gozos->count() >= $maxRows ? '+' : '' }}</span>
                            <span>| {{ $printedAt->format('d/m/Y H:i') }}</span>
                        </div>

                        <table class="pessoa-list-doc__table">
                            <thead>
                                <tr>
                                    @foreach ($columns as $column)
                                        <th>{{ $columnLabels[$column] ?? $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($gozos as $gozo)
                                    <tr>
                                        @foreach ($columns as $column)
                                            <td>{{ \App\Support\Erp\Reports\RhFeriasListagemReport::cellValue($gozo, $column, $alertaDias) }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columns) }}">Nenhum gozo encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
