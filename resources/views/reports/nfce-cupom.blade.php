<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle ?? ('NFC-e #' . str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT)) }}</title>
    <style>
        @page { margin: 4mm; size: 80mm auto; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 8px;
            max-width: 80mm;
            line-height: 1.3;
        }
        h1, h2 {
            margin: 0;
            text-align: center;
            font-size: 11px;
            line-height: 1.3;
        }
        .simulado {
            margin: 4px 0 6px;
            padding: 3px 5px;
            border: 1px dashed #000;
            text-align: center;
            font-weight: 700;
            font-size: 9px;
        }
        .simulado--real { border-style: solid; }
        .meta {
            margin-bottom: 6px;
            line-height: 1.3;
            text-align: center;
        }
        .meta--left { text-align: left; }
        .divider {
            border-top: 1px dashed #333;
            margin: 6px 0;
        }
        .itens {
            margin: 0 0 4px;
            white-space: pre;
            font-size: 8.5px;
            letter-spacing: -0.02em;
            line-height: 1.22;
            transform: scaleX(0.94);
            transform-origin: left center;
            width: 106.4%;
        }
        .itens .hdr {
            font-weight: 700;
            border-bottom: 1px dashed #333;
            margin-bottom: 2px;
            padding-bottom: 1px;
        }
        .itens .row { margin: 0 0 0.45em; }
        .totais {
            border-top: 1px dashed #333;
            padding-top: 4px;
            line-height: 1.4;
        }
        .totais div {
            display: flex;
            justify-content: space-between;
        }
        .totais .total-final {
            font-weight: 700;
            font-size: 11px;
        }
        .economizou {
            margin-top: 4px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
        }
        .fiscal-qr {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            width: 100%;
        }
        .fiscal-qr__code {
            flex: 0 0 46%;
            width: 46%;
            max-width: 46%;
            height: auto;
        }
        .fiscal-qr__code svg {
            width: 100% !important;
            height: auto !important;
            display: block;
        }
        .fiscal-qr__info {
            flex: 1 1 54%;
            min-width: 0;
            text-align: left;
            font-size: 10.5px;
            line-height: 1.35;
            font-weight: 600;
        }
        .fiscal-qr__info strong {
            display: block;
            font-size: 11px;
            font-weight: 700;
        }
        .chave {
            font-size: 10px;
            line-height: 1.3;
            word-break: break-all;
        }
        .barcode {
            display: block;
            width: 100%;
            max-height: 36px;
            object-fit: contain;
            margin: 3px 0;
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
    @unless ($embed ?? false)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

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

        <div @class(['simulado', 'simulado--real' => empty($simulada)])>{{ $ambienteLabel }}</div>

        <h2>DANFE NFC-e — Documento Auxiliar</h2>
        <div class="meta">
            <div>{{ $modoLabel }}</div>
            <div>{{ $statusLabel }}</div>
            <div>Emissão: {{ $dataEmissao }} {{ $horaEmissao }}</div>
            <div>PDV #{{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }} · Operador: {{ $usuario }}</div>
            @if ($venda->vendedor_nome)
                <div>Vendedor: {{ $venda->vendedor_nome }}</div>
            @endif
            @if ($consumidorNome ?? null)
                <div>Consumidor: {{ $consumidorNome }}</div>
                @if (filled($consumidorEndereco ?? null))
                    <div>Endereço: {{ $consumidorEndereco }}</div>
                @endif
            @endif
            @if ($cpfNotaMascarado ?? null)
                <div>CPF: {{ $cpfNotaMascarado }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <div class="itens">
            <div class="hdr">{{ $itemHeader }}</div>
            @foreach ($itemLines as $index => $linhas)
                @foreach ($linhas as $linha)
                    <div class="row">{{ $linha }}</div>
                @endforeach
            @endforeach
        </div>

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
            @if (($economizado ?? 0) > 0)
                <div class="economizou"><span>Voce economizou</span><span>R$ {{ number_format((float) $economizado, 2, ',', '') }}</span></div>
            @endif
        </div>

        @if ($venda->pagamentos->isNotEmpty())
            <div class="meta meta--left" style="margin-top: 4px;">
                <div><strong>Formas de pagamento</strong></div>
                @foreach ($venda->pagamentos as $pagamento)
                    <div>{{ $pagamento->descricaoComCanhoto() }}: R$ {{ number_format((float) $pagamento->valor, 2, ',', '') }}</div>
                @endforeach
            </div>
        @else
            <div class="meta meta--left" style="margin-top: 4px;">
                <div>Pagamento: {{ $venda->forma_pagamento }}</div>
            </div>
        @endif

        @if ($venda->observacoes)
            <div class="meta meta--left" style="margin-top: 4px;">Inf. adicionais: {{ $venda->observacoes }}</div>
        @endif

        <div class="divider"></div>

        @if ($barcodeDataUri)
            <img class="barcode" src="{{ $barcodeDataUri }}" alt="Código de barras da chave NFC-e">
        @endif

        <div class="fiscal-qr">
            <div class="fiscal-qr__code">{!! $qrSvg !!}</div>
            <div class="fiscal-qr__info">
                <strong>{{ ($simulada ?? true) ? 'Protocolo (simulado)' : 'Protocolo de autorização' }}</strong>
                <div>{{ $protocoloFormatado }}</div>
                <div>Nº {{ $numeroNf }}</div>
                <div>Série {{ $serie }} · Modelo {{ $modelo }}</div>
                <div style="margin-top: 3px;"><strong>Chave de acesso</strong></div>
                <div class="chave">
                    @foreach (\App\Support\Erp\Printing\EscPos\NfceQrInfoRaster::wrapChave((string) ($chave ?? ''), 20) as $chaveLinha)
                        <div>{{ $chaveLinha }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($obsNfce !== '')
            <div class="meta meta--left" style="margin-top: 4px;">{{ $obsNfce }}</div>
        @endif

        <div class="meta" style="margin-top: 6px;">
            @if (! empty($textoIbpt))
                <div class="meta meta--left">{{ $textoIbpt }}</div>
            @elseif (($vTotTrib ?? 0) > 0)
                <div class="meta meta--left">
                    Trib. aprox. Fed. R$ {{ number_format((float) ($tribFed ?? 0), 2, ',', '.') }}
                    · Est. R$ {{ number_format((float) ($tribEst ?? 0), 2, ',', '.') }}
                    · Mun. R$ {{ number_format((float) ($tribMun ?? 0), 2, ',', '.') }}
                    (Lei 12.741/2012 — IBPT)
                </div>
            @else
                <div class="meta meta--left">Tributos aprox. conforme Lei 12.741/2012 — IBPT.</div>
            @endif
            @unless ($simulada ?? true)
                <div>Consulte em sat.sef.sc.gov.br/nfce/consulta</div>
            @endunless
            <div>Impresso em {{ $printedAt->format('d/m/Y H:i:s') }}</div>
            <div style="margin-top: 4px; font-weight: 800; text-transform: uppercase;">DESENVOLVIDO POR UNITECNOLOGIA SISTEMAS LTDA</div>
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
