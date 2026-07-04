<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cupom PDV #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { margin: 8mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 12px;
            max-width: 80mm;
        }
        h1 {
            font-size: 13px;
            margin: 0 0 6px;
            text-align: center;
        }
        .meta {
            margin-bottom: 10px;
            line-height: 1.45;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            padding: 2px 0;
            vertical-align: top;
        }
        th {
            border-bottom: 1px dashed #333;
            font-size: 10px;
            text-align: left;
        }
        td.num, th.num { text-align: right; }
        .totais {
            border-top: 1px dashed #333;
            padding-top: 6px;
            line-height: 1.5;
        }
        .totais div {
            display: flex;
            justify-content: space-between;
        }
        .totais .total-final {
            font-weight: 700;
            font-size: 12px;
        }
        .toolbar { margin-bottom: 12px; }
        .via-label {
            text-align: center;
            font-size: 10px;
            margin: 12px 0 6px;
            border-top: 1px dashed #999;
            padding-top: 8px;
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

        <h1>{{ $empresa?->fantasia ?: ($empresa?->nome ?? 'UNITEC') }}</h1>
        <div class="meta">
            <div>CUPOM NÃO FISCAL</div>
            <div>Venda #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}</div>
            <div>{{ $printedAt->format('d/m/Y H:i:s') }}</div>
            <div>Operador: {{ $usuario }}</div>
            @if ($venda->vendedor_nome)
                <div>Vendedor: {{ $venda->vendedor_nome }}</div>
            @endif
            @if ($venda->person)
                <div>Cliente: {{ $venda->person->nome_razao }}</div>
            @endif
            @if ($venda->cpf_nota)
                <div>CPF/CNPJ: {{ $venda->cpf_nota }}</div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qtd</th>
                    <th class="num">Vlr</th>
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
            <div class="total-final"><span>TOTAL</span><span>R$ {{ number_format((float) $venda->total, 2, ',', '') }}</span></div>
            @if ((float) $venda->troco > 0)
                <div><span>Troco</span><span>R$ {{ number_format((float) $venda->troco, 2, ',', '') }}</span></div>
            @endif
            <div><span>Pagamento</span><span>{{ $venda->forma_pagamento }}</span></div>
        </div>

        @if ($venda->pagamentos->isNotEmpty())
            <div class="meta" style="margin-top: 8px; text-align: left;">
                @foreach ($venda->pagamentos as $pagamento)
                    <div>{{ $pagamento->forma }}: R$ {{ number_format((float) $pagamento->valor, 2, ',', '') }}</div>
                @endforeach
            </div>
        @endif

        @if ($venda->observacoes)
            <div class="meta" style="text-align: left; margin-top: 8px;">Obs: {{ $venda->observacoes }}</div>
        @endif

        <div class="meta" style="margin-top: 10px;">Obrigado pela preferência!</div>
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
