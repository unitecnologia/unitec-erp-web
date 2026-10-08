@php
    $pdf = (bool) ($pdf ?? false);
    $money = static fn ($valor): string => number_format((float) $valor, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e {{ $numeroNf }} — {{ $emitente['fantasia'] ?: $emitente['nome'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111;
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
        }
        .sheet { width: 100%; max-width: 190mm; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .box { border: 1px solid #222; margin-bottom: 3mm; }
        .box-title {
            padding: 1.2mm 2mm;
            border-bottom: 1px solid #222;
            background: #efefef;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .pad { padding: 2mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
        .muted { color: #444; }

        .emit td { padding: 2mm; }
        .emit__logo { width: 32mm; text-align: center; vertical-align: middle; }
        .emit__logo img { max-width: 30mm; max-height: 22mm; }
        .emit__nome { font-size: 13px; font-weight: bold; }
        .doc-title td { padding: 1.6mm 2mm; text-align: center; }
        .doc-title__main { font-size: 11px; font-weight: bold; }

        .alerta {
            margin-bottom: 3mm;
            padding: 2mm;
            border: 2px solid #111;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }

        .itens th {
            padding: 1.3mm 1.5mm;
            border-bottom: 1px solid #222;
            background: #efefef;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }
        .itens td { padding: 1.1mm 1.5mm; border-bottom: 1px solid #ddd; }
        .itens tr:last-child td { border-bottom: 0; }
        .itens .num { text-align: right; white-space: nowrap; }
        .w-cod { width: 16%; }
        .w-qtd { width: 9%; }
        .w-un { width: 6%; }
        .w-vu { width: 12%; }
        .w-vt { width: 12%; }

        .totais td { padding: 0.9mm 2mm; }
        .totais .pagar td { border-top: 1px solid #222; font-size: 11px; font-weight: bold; }
        .pag-head td { padding: 1.3mm 2mm 0.6mm; border-top: 1px solid #222; font-size: 8px; font-weight: bold; text-transform: uppercase; }

        .fiscal td { padding: 2mm; }
        .fiscal__qr { width: 46mm; text-align: center; vertical-align: middle; border-right: 1px solid #222; }
        .fiscal__qr img { width: 40mm; height: 40mm; }
        .fiscal__bloco { margin-bottom: 2.2mm; }
        .fiscal__bloco:last-child { margin-bottom: 0; }
        .fiscal__rotulo { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #333; }
        .chave { font-size: 10.5px; font-weight: bold; letter-spacing: 0.04em; }
        .url { word-break: break-all; }

        .rodape { margin-top: 2mm; font-size: 8px; text-align: center; color: #444; }
        .via-label { margin-bottom: 2mm; font-size: 8px; text-align: right; color: #444; }
        .page-break { page-break-before: always; }

        .toolbar {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar a, .toolbar button {
            padding: 7px 14px;
            border: 1px solid #1e5a9e;
            border-radius: 6px;
            background: #1e5a9e;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar a { background: #fff; color: #1e5a9e; }
        @if ($pdf)
            body { background: #fff; }
            .sheet { width: auto; max-width: none; margin: 0; padding: 0; }
        @else
            @media screen {
                body { background: #e5e7eb; }
                .sheet { margin: 12px auto; padding: 10mm; background: #fff; box-shadow: 0 2px 10px rgb(0 0 0 / 15%); }
            }
        @endif
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .sheet { margin: 0 auto; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    @unless ($pdf || ($embed ?? false))
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir</button>
            <a href="{{ request()->fullUrlWithQuery(['pdf' => 1, 'auto' => 0]) }}" target="_blank" rel="noopener">Baixar PDF</a>
        </div>
    @endunless

    @for ($via = 1; $via <= $copias; $via++)
        <div @class(['sheet', 'page-break' => $via > 1])>
            @if ($copias > 1)
                <div class="via-label">Via {{ $via }} de {{ $copias }}</div>
            @endif

            {{-- Divisão I — Cabeçalho (emitente) --}}
            <div class="box">
                <table class="emit">
                    <tr>
                        @if (! empty($logoDataUri))
                            <td class="emit__logo"><img src="{{ $logoDataUri }}" alt="Logo"></td>
                        @endif
                        <td>
                            <div class="emit__nome">{{ $emitente['nome'] }}</div>
                            @if (($emitente['fantasia'] ?? '') !== '' && $emitente['fantasia'] !== $emitente['nome'])
                                <div>{{ $emitente['fantasia'] }}</div>
                            @endif
                            <div>CNPJ: {{ $emitente['cnpj'] }}@if (($emitente['ie'] ?? '') !== '') &nbsp;&nbsp; Inscrição Estadual: {{ $emitente['ie'] }}@endif</div>
                            <div>{{ $emitente['endereco'] }}</div>
                            <div>{{ $emitente['municipio'] }}@if (($emitente['uf'] ?? '') !== '') — {{ $emitente['uf'] }}@endif @if (($emitente['telefone'] ?? '') !== '') &nbsp;&nbsp; Fone: {{ $emitente['telefone'] }}@endif</div>
                        </td>
                    </tr>
                </table>
                <table class="doc-title" style="border-top: 1px solid #222;">
                    <tr>
                        <td>
                            <div class="doc-title__main">DANFE NFC-e — Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div>
                        </td>
                    </tr>
                </table>
            </div>

            @if ($simulada)
                <div class="alerta">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_SIMULADA }}</div>
            @elseif ($homologacao)
                <div class="alerta">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_HOMOLOGACAO }}</div>
            @endif
            @if ($cancelada)
                <div class="alerta">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_CANCELADA }}</div>
            @endif
            @if ($contingencia)
                <div class="alerta">
                    {{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_CONTINGENCIA }}
                    @if ($pendenteAutorizacao)
                        <div style="font-size: 10px;">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_PENDENTE }}</div>
                    @endif
                </div>
            @endif

            {{-- Divisão II — Detalhe da venda --}}
            <div class="box">
                <table class="itens">
                    <thead>
                        <tr>
                            <th class="w-cod">Código</th>
                            <th>Descrição</th>
                            <th class="w-qtd num">Qtde</th>
                            <th class="w-un">UN</th>
                            <th class="w-vu num">Vl. Unit.</th>
                            <th class="w-vt num">Vl. Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itensA4 as $item)
                            <tr>
                                <td class="mono">{{ $item['codigo'] }}</td>
                                <td>{{ $item['descricao'] }}</td>
                                <td class="num">{{ $item['quantidade'] }}</td>
                                <td>{{ $item['unidade'] }}</td>
                                <td class="num">{{ $money($item['unitario']) }}</td>
                                <td class="num">{{ $money($item['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Divisão III — Totais e pagamento --}}
            <div class="box">
                <table class="totais">
                    <tr><td>Qtde. total de itens</td><td class="right">{{ $qtdItens }}</td></tr>
                    <tr><td>Valor total R$</td><td class="right">{{ $money($totaisA4['valor_total']) }}</td></tr>
                    @if ($totaisA4['descontos'] > 0)
                        <tr><td>Descontos R$</td><td class="right">-{{ $money($totaisA4['descontos']) }}</td></tr>
                    @endif
                    @if ($totaisA4['acrescimos'] > 0)
                        <tr><td>Acréscimos R$</td><td class="right">{{ $money($totaisA4['acrescimos']) }}</td></tr>
                    @endif
                    <tr class="pagar"><td>Valor a pagar R$</td><td class="right">{{ $money($totaisA4['valor_pagar']) }}</td></tr>
                    <tr class="pag-head"><td>Forma de pagamento</td><td class="right">Valor pago R$</td></tr>
                    @foreach ($pagamentosA4 as $pagamento)
                        <tr><td>{{ $pagamento['forma'] }}</td><td class="right">{{ $money($pagamento['valor']) }}</td></tr>
                    @endforeach
                    @if ($trocoA4 > 0)
                        <tr><td>Troco R$</td><td class="right">{{ $money($trocoA4) }}</td></tr>
                    @endif
                </table>
            </div>

            {{-- Divisões IV a VII — Consulta, consumidor, identificação e QR Code --}}
            <div class="box">
                <table class="fiscal">
                    <tr>
                        <td class="fiscal__qr">
                            @if (! empty($qrImagemDataUri))
                                <img src="{{ $qrImagemDataUri }}" alt="QR Code da NFC-e">
                            @endif
                            <div class="muted" style="margin-top: 1mm; font-size: 7.5px;">Consulta via leitor de QR Code</div>
                        </td>
                        <td>
                            <div class="fiscal__bloco">
                                <div class="fiscal__rotulo">Consulte pela chave de acesso em</div>
                                @if ($urlConsulta !== '')
                                    <div class="url">{{ $urlConsulta }}</div>
                                @else
                                    <div class="muted">Portal da NFC-e da Secretaria da Fazenda da UF do emitente</div>
                                @endif
                                <div class="chave mono">{{ $chaveBlocos }}</div>
                            </div>

                            <div class="fiscal__bloco">
                                <div class="fiscal__rotulo">Consumidor</div>
                                @if ($consumidorIdentificado)
                                    <div>{{ $consumidorDocumento }}@if (filled($consumidorNome ?? null)) — {{ $consumidorNome }}@endif</div>
                                    @if (filled($consumidorEndereco ?? null))
                                        <div>{{ $consumidorEndereco }}</div>
                                    @endif
                                @else
                                    <div>CONSUMIDOR NÃO IDENTIFICADO</div>
                                @endif
                            </div>

                            <div class="fiscal__bloco">
                                <div class="fiscal__rotulo">Identificação da NFC-e</div>
                                <div class="bold">NFC-e nº {{ $numeroNf }} &nbsp; Série {{ $serie }} &nbsp; {{ $dataEmissaoA4 }} {{ $horaEmissaoA4 }}</div>
                                @if ($protocoloA4 !== '')
                                    <div>Protocolo de autorização: <span class="mono">{{ $protocoloA4 }}</span></div>
                                    @if ($dhAutorizacaoA4 !== '')
                                        <div>Data de autorização: {{ $dhAutorizacaoA4 }}</div>
                                    @endif
                                @elseif ($simulada)
                                    <div class="muted">Sem protocolo — documento simulado</div>
                                @endif
                                @if ($contingencia)
                                    <div class="bold">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_CONTINGENCIA }}@if ($pendenteAutorizacao) — {{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_PENDENTE }}@endif</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Divisão VIII — Informações de interesse do contribuinte --}}
            <div class="box">
                <div class="box-title">Informações complementares</div>
                <div class="pad">
                    <div>{{ $tributosTexto !== '' ? $tributosTexto : 'Tributos aprox. conforme Lei 12.741/2012. Fonte: IBPT.' }}</div>
                    @if (! empty($mensagemCreditoDanfeNfce))
                        <div class="bold" style="margin-top: 1mm; text-transform: uppercase;">{{ $mensagemCreditoDanfeNfce }}</div>
                    @endif
                    @if (! empty($mensagensLegaisNfce) && is_array($mensagensLegaisNfce))
                        @foreach ($mensagensLegaisNfce as $mensagemLegal)
                            @if ($mensagemLegal !== ($mensagemCreditoDanfeNfce ?? null))
                                <div style="margin-top: 1mm;">{{ $mensagemLegal }}</div>
                            @endif
                        @endforeach
                    @endif
                    @if (($obsNfce ?? '') !== '')
                        <div style="margin-top: 1mm;">{{ $obsNfce }}</div>
                    @endif
                    @if (filled($venda->observacoes))
                        <div style="margin-top: 1mm;">{{ $venda->observacoes }}</div>
                    @endif
                    <div class="muted" style="margin-top: 1.5mm;">
                        Operador: {{ $usuario }}
                        &nbsp;·&nbsp; DAV: {{ $numeroPdv }}
                        @if (filled($numeroPedido ?? null)) &nbsp;·&nbsp; Pedido: {{ $numeroPedido }} @endif
                        @if (filled($vendedorNome ?? null)) &nbsp;·&nbsp; Vendedor: {{ $vendedorNome }} @endif
                    </div>
                </div>
            </div>

            <div class="rodape">
                Impresso em {{ $printedAt->format('d/m/Y H:i:s') }} &nbsp;·&nbsp; DESENVOLVIDO POR UNITECNOLOGIA SISTEMAS LTDA
            </div>
        </div>
    @endfor

    @if ($autoPrint && ! $pdf)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
