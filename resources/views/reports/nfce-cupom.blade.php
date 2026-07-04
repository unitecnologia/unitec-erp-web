<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>NFC-e Simulada #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</title>
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
        .simulado {
            margin: 6px 0 8px;
            padding: 4px 6px;
            border: 1px dashed #000;
            text-align: center;
            font-weight: 700;
            font-size: 9px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        th, td {
            padding: 2px 0;
            vertical-align: top;
        }
        th {
            border-bottom: 1px dashed #333;
            font-size: 9px;
            text-align: left;
        }
        td.num, th.num { text-align: right; }
        .totais {
            border-top: 1px dashed #333;
            padding-top: 6px;
            line-height: 1.45;
        }
        .totais div {
            display: flex;
            justify-content: space-between;
        }
        .totais .total-final {
            font-weight: 700;
            font-size: 11px;
        }
        .chave {
            font-size: 9px;
            line-height: 1.35;
            word-break: break-word;
            text-align: center;
        }
        .barcode {
            display: block;
            width: 100%;
            max-height: 52px;
            object-fit: contain;
            margin: 4px auto;
        }
        .qr {
            display: block;
            width: 120px;
            height: 120px;
            margin: 6px auto;
        }
        .toolbar { margin-bottom: 10px; }
        .via-label {
            text-align: center;
            font-size: 9px;
            margin: 10px 0 6px;
            border-top: 1px dashed #999;
            padding-top: 6px;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @for ($via = 1; $via <= $copias; $via++)
        @if ($via > 1)
            <div class="page-break"></div>
        @endif

        @if ($copias > 1)
            <div class="via-label">Via {{ $via }} de {{ $copias }}</div>
        @endif

        <h1>{{ $emitente['fantasia'] ?: $emitente['nome'] }}</h1>
        <div class="meta">
            <div>{{ $emitente['nome'] }}</div>
            <div>CNPJ: {{ $emitente['cnpj'] }} IE: {{ $emitente['ie'] }}</div>
            <div>{{ $emitente['endereco'] }}</div>
            <div>{{ $emitente['municipio'] }} — {{ $emitente['uf'] }}</div>
            @if ($emitente['telefone'] !== '')
                <div>Fone: {{ $emitente['telefone'] }}</div>
            @endif
        </div>

        <div class="simulado">{{ $ambienteLabel }}</div>

        <h2>DANFE NFC-e — Documento Auxiliar</h2>
        <div class="meta">
            <div>{{ $modoLabel }}</div>
            <div>{{ $statusLabel }}</div>
            <div>Nº {{ $numeroNf }} Série {{ $serie }} Modelo {{ $modelo }}</div>
            <div>Emissão: {{ $dataEmissao }} {{ $horaEmissao }}</div>
            <div>PDV #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</div>
            <div>Operador: {{ $usuario }}</div>
            @if ($venda->vendedor_nome)
                <div>Vendedor: {{ $venda->vendedor_nome }}</div>
            @endif
            @if ($venda->person)
                <div>Consumidor: {{ $venda->person->nome_razao }}</div>
            @endif
            @if ($venda->cpf_nota)
                <div>CPF: {{ $venda->cpf_nota }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <table>
            <thead>
                <tr>
                    <th>Cód</th>
                    <th class="num">Qtd</th>
                    <th class="num">Unit</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($venda->itens as $item)
                    <tr>
                        <td colspan="4">{{ $item->descricao }}</td>
                    </tr>
                    <tr>
                        <td>{{ $item->codigo }}</td>
                        <td class="num">
                            @php $qtd = (float) $item->quantidade; @endphp
                            {{ fmod($qtd, 1.0) === 0.0 ? (int) $qtd : number_format($qtd, 3, ',', '') }}
                        </td>
                        <td class="num">{{ number_format((float) $item->preco_unitario, 2, ',', '') }}</td>
                        <td class="num">{{ number_format((float) $item->total, 2, ',', '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totais">
            <div><span>Subtotal</span><span>R$ {{ number_format((float) $venda->subtotal, 2, ',', '') }}</span></div>
            @if ((float) $venda->desconto > 0)
                <div><span>Desconto</span><span>R$ {{ number_format((float) $venda->desconto, 2, ',', '') }}</span></div>
            @endif
            @if ((float) $venda->acrescimo > 0)
                <div><span>Acréscimo</span><span>R$ {{ number_format((float) $venda->acrescimo, 2, ',', '') }}</span></div>
            @endif
            <div class="total-final"><span>TOTAL NFC-e</span><span>R$ {{ number_format((float) $venda->total, 2, ',', '') }}</span></div>
            @if ((float) $venda->troco > 0)
                <div><span>Troco</span><span>R$ {{ number_format((float) $venda->troco, 2, ',', '') }}</span></div>
            @endif
        </div>

        @if ($venda->pagamentos->isNotEmpty())
            <div class="meta meta--left" style="margin-top: 6px;">
                <div><strong>Formas de pagamento</strong></div>
                @foreach ($venda->pagamentos as $pagamento)
                    <div>{{ $pagamento->forma }}: R$ {{ number_format((float) $pagamento->valor, 2, ',', '') }}</div>
                @endforeach
            </div>
        @else
            <div class="meta meta--left" style="margin-top: 6px;">
                <div>Pagamento: {{ $venda->forma_pagamento }}</div>
            </div>
        @endif

        @if ($venda->observacoes)
            <div class="meta meta--left" style="margin-top: 6px;">Inf. adicionais: {{ $venda->observacoes }}</div>
        @endif

        <div class="divider"></div>

        <div class="meta">
            <div><strong>Protocolo (simulado)</strong></div>
            <div>{{ $protocoloFormatado }}</div>
        </div>

        <div class="meta" style="margin-top: 6px;">
            <div><strong>Consulta pela chave de acesso</strong></div>
        </div>
        <div class="chave">{{ $chaveFormatada }}</div>

        @if ($barcodeDataUri)
            <img class="barcode" src="{{ $barcodeDataUri }}" alt="Código de barras da chave NFC-e">
        @endif

        <div class="qr">{!! $qrSvg !!}</div>

        @if ($obsNfce !== '')
            <div class="meta meta--left" style="margin-top: 6px;">{{ $obsNfce }}</div>
        @endif

        <div class="meta" style="margin-top: 8px;">
            <div>Tributos aprox. conforme Lei 12.741/2012 (simulado)</div>
            <div>Impresso em {{ $printedAt->format('d/m/Y H:i:s') }}</div>
        </div>
    @endfor

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
