<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Escala mensal — RH</title>
    <link rel="stylesheet" href="{{ asset('css/erp-rh-escalas.css') }}?v={{ config('unitec.versao') }}">
    <style>
        @page { margin: 10mm; size: A4 portrait; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e2e8f0; }
        .viewer__toolbar { display: flex; gap: 0.5rem; padding: 0.5rem; background: #f1f5f9; border-bottom: 1px solid #94a3b8; }
        .viewer__btn { padding: 0.35rem 0.75rem; border: 1px solid #94a3b8; border-radius: 4px; background: #fff; cursor: pointer; font-weight: 700; font-size: 0.8rem; }
        .print-doc { max-width: 210mm; margin: 1rem auto; background: #fff; padding: 12mm; }
        .print-doc h1 { font-size: 1rem; margin: 0 0 0.5rem; }
        .print-doc__meta { font-size: 0.75rem; color: #475569; margin-bottom: 0.75rem; }
        @media print {
            .viewer__toolbar { display: none; }
            body { background: #fff; }
            .print-doc { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="viewer__toolbar">
        <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
        <button type="button" class="viewer__btn" onclick="window.location.href=@js($closeUrl)">Fechar</button>
    </div>

    <div class="print-doc">
        <h1>{{ $reportTitle }}</h1>
        <div class="print-doc__meta">
            {{ mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome_fantasia ?: ''), 'UTF-8') }}
            @if ($funcionario)
                | {{ trim($funcionario->codigo.' — '.$funcionario->nome) }}
            @endif
            | {{ $mesLabel }} / {{ $ano }} | {{ $printedAt->format('d/m/Y H:i') }}
        </div>

        @php
            $offset = count($dias) > 0 ? (int) (\Carbon\Carbon::parse($dias[0]['data'])->dayOfWeek) : 0;
            $cells = array_merge(array_fill(0, $offset, null), $dias);
            while (count($cells) % 7 !== 0) { $cells[] = null; }
            $semanas = array_chunk($cells, 7);
        @endphp

        <table class="erp-rh-escalas-cal__table">
            <thead>
                <tr>
                    @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $hd)
                        <th>{{ $hd }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($semanas as $semana)
                    <tr>
                        @foreach ($semana as $cel)
                            <td class="erp-rh-escalas-cal__cell @if($cel) erp-rh-escalas-cal__cell--{{ $cel['tipo'] }} @endif">
                                @if ($cel)
                                    <strong>{{ $cel['dia'] }}</strong> — {{ $cel['label'] }}
                                    @if (filled($cel['hora_inicio']))
                                        <br><small>{{ $cel['hora_inicio'] }}–{{ $cel['hora_fim'] ?? '' }}</small>
                                    @endif
                                    @if ($cel['ajuste'])
                                        <br><small>* ajuste</small>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
