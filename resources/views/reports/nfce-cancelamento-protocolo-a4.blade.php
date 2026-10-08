<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Protocolo de Cancelamento NFC-e {{ $numeroNf }} — {{ $emitente['fantasia'] ?: $emitente['nome'] }}</title>
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
        .bold { font-weight: bold; }
        .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
        .muted { color: #444; }

        .emit td { padding: 2mm; }
        .emit__logo { width: 32mm; text-align: center; vertical-align: middle; }
        .emit__logo img { max-width: 30mm; max-height: 22mm; }
        .emit__nome { font-size: 13px; font-weight: bold; }
        .doc-title td { padding: 1.6mm 2mm; text-align: center; }
        .doc-title__main { font-size: 12px; font-weight: bold; letter-spacing: 0.03em; }
        .doc-title__sub { font-size: 9px; color: #333; }

        .alerta {
            margin-bottom: 3mm;
            padding: 2mm;
            border: 2px solid #111;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }
        .situacao {
            margin-bottom: 3mm;
            padding: 2.2mm;
            border: 2px solid #b91c1c;
            color: #b91c1c;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.06em;
        }

        .campos td { padding: 1.6mm 2mm; border-bottom: 1px solid #ddd; }
        .campos tr:last-child td { border-bottom: 0; }
        .campos td + td { border-left: 1px solid #ddd; }
        .rotulo { display: block; font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: #333; }
        .valor { display: block; font-size: 10px; }
        .valor--destaque { font-size: 11.5px; font-weight: bold; }
        .chave { font-size: 11px; font-weight: bold; letter-spacing: 0.04em; }
        .justificativa { font-size: 10px; white-space: pre-wrap; word-break: break-word; }

        .rodape { margin-top: 2mm; font-size: 8px; text-align: center; color: #444; }

        .toolbar {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar button {
            padding: 7px 14px;
            border: 1px solid #1e5a9e;
            border-radius: 6px;
            background: #1e5a9e;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        @media screen {
            body { background: #e5e7eb; }
            .sheet { margin: 12px auto; padding: 10mm; background: #fff; box-shadow: 0 2px 10px rgb(0 0 0 / 15%); }
        }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .sheet { margin: 0 auto; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    @unless ($embed)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

    <div class="sheet">
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
                        @if (($emitente['endereco'] ?? '') !== '')
                            <div>{{ $emitente['endereco'] }}</div>
                        @endif
                        <div>{{ $emitente['municipio'] }}@if (($emitente['uf'] ?? '') !== '') — {{ $emitente['uf'] }}@endif @if (($emitente['telefone'] ?? '') !== '') &nbsp;&nbsp; Fone: {{ $emitente['telefone'] }}@endif</div>
                    </td>
                </tr>
            </table>
            <table class="doc-title" style="border-top: 1px solid #222;">
                <tr>
                    <td>
                        <div class="doc-title__main">PROTOCOLO DE CANCELAMENTO DE NFC-e</div>
                        <div class="doc-title__sub">Evento 110111 — Cancelamento da Nota Fiscal de Consumidor Eletrônica</div>
                    </td>
                </tr>
            </table>
        </div>

        @if ($homologacao)
            <div class="alerta">{{ \App\Support\Erp\Nfce\NfceDanfeA4Data::MSG_HOMOLOGACAO }}</div>
        @endif

        <div class="situacao">NFC-e CANCELADA</div>

        <div class="box">
            <div class="box-title">Identificação da NFC-e</div>
            <table class="campos">
                <tr>
                    <td style="width: 25%;">
                        <span class="rotulo">Número</span>
                        <span class="valor valor--destaque">{{ $numeroNf }}</span>
                    </td>
                    <td style="width: 15%;">
                        <span class="rotulo">Série</span>
                        <span class="valor valor--destaque">{{ $serie }}</span>
                    </td>
                    <td style="width: 12%;">
                        <span class="rotulo">Modelo</span>
                        <span class="valor">{{ $modelo }}</span>
                    </td>
                    <td style="width: 24%;">
                        <span class="rotulo">Emissão</span>
                        <span class="valor">{{ $dataEmissao ?: '—' }}</span>
                    </td>
                    <td>
                        <span class="rotulo">Valor total R$</span>
                        <span class="valor">{{ $valorTotal }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="5">
                        <span class="rotulo">Chave de acesso</span>
                        <span class="valor chave mono">{{ $chaveBlocos }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="5">
                        <span class="rotulo">Protocolo de autorização</span>
                        <span class="valor mono">{{ $protocoloAutorizacaoFormatado ?: '—' }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">Evento de cancelamento</div>
            <table class="campos">
                <tr>
                    <td style="width: 40%;">
                        <span class="rotulo">Protocolo de cancelamento</span>
                        <span class="valor valor--destaque mono">{{ $protocoloFormatado ?: '—' }}</span>
                    </td>
                    <td style="width: 30%;">
                        <span class="rotulo">Data/hora do cancelamento</span>
                        <span class="valor valor--destaque">{{ $dataHoraEvento ?: $dataCancelamento.' '.$horaCancelamento }}</span>
                    </td>
                    <td>
                        <span class="rotulo">Retorno SEFAZ</span>
                        <span class="valor">{{ $retornoSefaz ?: '—' }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">Justificativa do cancelamento</div>
            <div class="pad justificativa">{{ $justificativa !== '' ? $justificativa : '—' }}</div>
        </div>

        <div class="box">
            <div class="box-title">Informações complementares</div>
            <div class="pad muted">
                Operador: {{ $usuario }}
                &nbsp;·&nbsp; DAV: {{ str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT) }}
                <div style="margin-top: 1mm;">Consulte a situação da NFC-e pela chave de acesso no portal da SEFAZ da UF do emitente.</div>
            </div>
        </div>

        <div class="rodape">
            Impresso em {{ $printedAt->format('d/m/Y H:i:s') }} &nbsp;·&nbsp; DESENVOLVIDO POR UNITECNOLOGIA SISTEMAS LTDA
        </div>
    </div>

    @if ($autoPrint && ! $embed)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 300);
            });
        </script>
    @endif
</body>
</html>
