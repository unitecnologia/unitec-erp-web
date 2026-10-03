<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Histórico resumido — {{ $veiculo['placa'] }}</title>
    <style>
        @page { margin: 8mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #c7d5e8; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        .viewer__toolbar { display: flex; gap: 0.35rem; align-items: center; padding: 0.4rem 0.6rem; background: #f8fafc; border-bottom: 1px solid #94a3b8; }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn { min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px; background: #fff; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
        .viewer__canvas { padding: 1rem; }
        .sheet { width: min(297mm, 100%); margin: 0 auto; background: #fff; padding: 6mm; }
        h1 { margin: 0 0 0.15rem; font-size: 13pt; }
        .empresa { margin: 0 0 0.15rem; font-size: 9pt; color: #334155; }
        .ident { margin: 0 0 0.45rem; font-size: 9pt; }
        .ident strong { margin-right: 0.35rem; }
        .ident span + span::before { content: "·"; margin: 0 0.35rem; color: #64748b; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th, td { border: 1px solid #cbd5e1; padding: 0.12rem 0.28rem; text-align: left; vertical-align: top; }
        th { background: #0f3460; color: #fff; font-weight: 700; white-space: nowrap; }
        td.num, th.num, td.din, th.din { text-align: right; white-space: nowrap; }
        td.problema { max-width: 7.5cm; }
        tfoot td { background: #f1f5f9; font-weight: 700; }
        .vazio { margin: 0.4rem 0 0; font-size: 9pt; color: #64748b; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar { display: none !important; }
            .viewer__canvas { padding: 0; }
            .sheet { width: auto; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="viewer__toolbar">
        <span class="viewer__title">Histórico resumido — {{ $veiculo['placa'] }}</span>
        <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
        <button type="button" class="viewer__btn" onclick="closePreview()">Fechar</button>
    </div>
    <div class="viewer__canvas">
        <article class="sheet">
            @if ($empresa !== '')
                <p class="empresa">{{ $empresa }}</p>
            @endif
            <h1>Histórico resumido de manutenções</h1>
            <p class="ident">
                <strong>{{ $veiculo['placa'] }}</strong>
                @if ($veiculo['placa_alternativa'] !== '')
                    <span>Placa alt. {{ $veiculo['placa_alternativa'] }}</span>
                @endif
                @if ($veiculo['descricao'] !== '')
                    <span>{{ $veiculo['descricao'] }}</span>
                @endif
                @if ($veiculo['modelo'] !== '')
                    <span>{{ $veiculo['modelo'] }}</span>
                @endif
                @if ($veiculo['ano'] !== '')
                    <span>{{ $veiculo['ano'] }}</span>
                @endif
            </p>

            @if ($manutencoes === [])
                <p class="vazio">Nenhuma manutenção encontrada para este veículo.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th class="num">KM</th>
                            <th>Técnico</th>
                            <th>Defeito/Problema</th>
                            <th>Situação</th>
                            <th class="din">Peças</th>
                            <th class="din">Serviços</th>
                            <th class="din">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($manutencoes as $os)
                            <tr>
                                <td>{{ $os['numero'] }}</td>
                                <td>{{ $os['data'] }}</td>
                                <td>{{ $os['cliente'] }}</td>
                                <td class="num">{{ $os['km'] }}</td>
                                <td>{{ $os['tecnico'] }}</td>
                                <td class="problema">{{ $os['problema'] }}</td>
                                <td>{{ $os['situacao'] }}</td>
                                <td class="din">{{ $os['pecas'] }}</td>
                                <td class="din">{{ $os['servicos'] }}</td>
                                <td class="din">{{ $os['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">{{ $totais['quantidade'] }} {{ $totais['quantidade'] === 1 ? 'manutenção' : 'manutenções' }}</td>
                            <td class="din">{{ $totais['pecas'] }}</td>
                            <td class="din">{{ $totais['servicos'] }}</td>
                            <td class="din">{{ $totais['geral'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </article>
    </div>
    <script>
        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-os-veiculo-historico-preview-close' }, '*');
                return;
            }
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.close();
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePreview();
            }
        });
    </script>
</body>
</html>
