<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Protocolo Cancelamento NFC-e #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { margin: 6mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 10px;
            max-width: 80mm;
        }
        h1, h2 {
            margin: 0;
            text-align: center;
            font-size: 11px;
            line-height: 1.35;
        }
        .meta {
            margin-bottom: 8px;
            line-height: 1.4;
            text-align: center;
        }
        .meta--left { text-align: left; }
        .divider {
            border-top: 1px dashed #333;
            margin: 8px 0;
        }
        .protocolo {
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            word-break: break-all;
            margin: 8px 0;
        }
        .chave {
            font-size: 9px;
            line-height: 1.35;
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
    <div class="meta">
        <div>{{ $emitente['nome'] }}</div>
        <div>CNPJ: {{ $emitente['cnpj'] }} IE: {{ $emitente['ie'] }}</div>
        <div>{{ $emitente['endereco'] }}</div>
        <div>{{ $emitente['municipio'] }} — {{ $emitente['uf'] }}</div>
    </div>

    <h2>PROTOCOLO DE CANCELAMENTO NFC-e</h2>

    <div class="meta">
        <div>PDV #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</div>
        <div>NFC-e Nº {{ $numeroNf }} Série {{ $serie }}</div>
        <div>Cancelada em {{ $dataCancelamento }} {{ $horaCancelamento }}</div>
        <div>Operador: {{ $usuario }}</div>
    </div>

    <div class="divider"></div>

    <div class="meta"><strong>Protocolo de cancelamento</strong></div>
    <div class="protocolo">{{ $protocoloFormatado }}</div>
    <div class="protocolo">{{ $protocolo }}</div>

    @if ($motivoEstorno !== '')
        <div class="meta meta--left" style="margin-top: 8px;">
            <div><strong>Justificativa</strong></div>
            <div>{{ $motivoEstorno }}</div>
        </div>
    @endif

    <div class="divider"></div>

    <div class="meta">
        <div><strong>Chave de acesso</strong></div>
    </div>
    <div class="chave">{{ $chaveFormatada }}</div>

    <div class="meta" style="margin-top: 8px;">
        <div>Impresso em {{ $printedAt->format('d/m/Y H:i:s') }}</div>
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
