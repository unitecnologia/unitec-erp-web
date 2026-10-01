<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório NFS-e {{ $periodo['labelShort'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; margin: 12mm; }
        h1 { font-size: 13pt; margin: 0 0 4px; }
        .meta { font-size: 8.5pt; color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #94a3b8; padding: 3px 5px; }
        th { background: #e2e8f0; font-size: 8pt; text-align: left; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tfoot td { font-weight: 700; background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>RELATÓRIO DE NFS-e — {{ $periodo['label'] }}</h1>
    <div class="meta">
        {{ mb_strtoupper($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: 'EMPRESA', 'UTF-8') }}
        · Gerado em {{ $printedAt->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Número</th>
                <th style="width: 10%;">Emissão</th>
                <th style="width: 34%;">Tomador</th>
                <th style="width: 26%;">Chave</th>
                <th style="width: 10%;" class="center">Situação</th>
                <th style="width: 10%;" class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['numero'] }}</td>
                    <td class="center">{{ $row['emissao'] }}</td>
                    <td>{{ $row['tomador'] }}</td>
                    <td>{{ $row['chave'] }}</td>
                    <td class="center">{{ $row['status'] }}</td>
                    <td class="num">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Nenhuma NFS-e no período.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL</td>
                <td class="num">{{ $grandTotal }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
