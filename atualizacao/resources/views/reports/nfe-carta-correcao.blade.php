<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>CC-e NF-e {{ $numeroNota }} Seq. {{ $sequencia }}</title>
    <style>
        @page { margin: 12mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 16px;
        }
        h1, h2 {
            margin: 0;
            text-align: center;
            line-height: 1.35;
        }
        h1 { font-size: 14px; }
        h2 { font-size: 13px; margin-top: 10px; }
        .meta {
            margin-bottom: 8px;
            line-height: 1.45;
        }
        .meta--center { text-align: center; }
        .divider {
            border-top: 1px solid #333;
            margin: 10px 0;
        }
        .box {
            border: 1px solid #333;
            padding: 10px;
            margin: 10px 0;
            line-height: 1.5;
        }
        .box__title {
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .protocolo {
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            word-break: break-all;
            margin: 6px 0;
        }
        .chave {
            font-size: 10px;
            line-height: 1.4;
            word-break: break-word;
            text-align: center;
        }
        .toolbar { margin-bottom: 10px; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <h1>{{ $emitente['fantasia'] ?: $emitente['nome'] }}</h1>
    <div class="meta meta--center">
        <div>{{ $emitente['nome'] }}</div>
        <div>CNPJ: {{ $emitente['cnpj'] }} | IE: {{ $emitente['ie'] }}</div>
        <div>{{ $emitente['endereco'] }}</div>
        <div>{{ $emitente['municipio'] }} — {{ $emitente['uf'] }}</div>
    </div>

    <h2>CARTA DE CORREÇÃO ELETRÔNICA — CC-e</h2>

    <div class="meta">
        <div><strong>NF-e:</strong> Nº {{ $numeroNota }} | Série {{ $serie }}</div>
        <div><strong>Destinatário:</strong> {{ $destinatario['nome'] }} ({{ $destinatario['documento'] }})</div>
        <div><strong>Sequência do evento:</strong> {{ $sequencia }}</div>
        <div><strong>Data/hora do evento:</strong> {{ $dataEvento }} {{ $horaEvento }}</div>
        @if ($protocoloNfe !== '')
            <div><strong>Protocolo NF-e:</strong> {{ $protocoloNfe }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <div class="box">
        <div class="box__title">Correção</div>
        <div>{{ $correcao }}</div>
    </div>

    <div class="box">
        <div class="box__title">Condições de uso</div>
        <div>{{ $condicoesUso }}</div>
    </div>

    <div class="divider"></div>

    <div class="meta meta--center"><strong>Protocolo de autorização do evento</strong></div>
    <div class="protocolo">{{ $protocoloFormatado }}</div>
    <div class="protocolo">{{ $protocoloEvento }}</div>

    <div class="divider"></div>

    <div class="meta meta--center"><strong>Chave de acesso da NF-e</strong></div>
    <div class="chave">{{ $chaveFormatada }}</div>

    <div class="meta meta--center" style="margin-top: 12px;">
        <div>Impresso em {{ $printedAt->format('d/m/Y H:i:s') }}</div>
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif

    <script>
        window.addEventListener('afterprint', () => {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-nfe-danfe-print-done' }, '*');
            }
        });
    </script>
</body>
</html>
