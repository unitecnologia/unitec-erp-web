<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Boleto {{ $nosso_numero !== '' ? $nosso_numero : $boleto->id }}</title>
    <style>
        @page { margin: 8mm 8mm 8mm 8mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
        }
        table { border-collapse: collapse; width: 100%; }
        .cut {
            border-top: 1px dashed #666;
            margin: 8px 0 10px;
            padding-top: 4px;
            font-size: 8px;
            color: #555;
            text-align: center;
        }
        .hdr td { vertical-align: middle; }
        .logo {
            width: 110px;
            max-height: 28px;
        }
        .bank-code {
            font-size: 16px;
            font-weight: 700;
            border-left: 2px solid #111;
            border-right: 2px solid #111;
            padding: 0 8px;
            white-space: nowrap;
        }
        .linha {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-align: right;
        }
        .grid td {
            border: 1px solid #222;
            padding: 2px 4px;
            vertical-align: top;
            height: 28px;
        }
        .lbl {
            display: block;
            font-size: 7px;
            color: #444;
            text-transform: uppercase;
            margin-bottom: 1px;
        }
        .val {
            display: block;
            font-size: 10px;
            font-weight: 700;
            min-height: 12px;
        }
        .val-sm { font-size: 9px; font-weight: 600; }
        .right { text-align: right; }
        .instr {
            min-height: 70px;
            font-size: 9px;
            line-height: 1.35;
        }
        .instr li { margin: 0 0 2px 14px; }
        .sacado { min-height: 42px; }
        .barcode {
            margin-top: 8px;
            text-align: left;
        }
        .barcode img {
            height: 48px;
            max-width: 100%;
        }
        .muted { color: #555; font-size: 8px; }
        .section-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 4px;
            color: #333;
        }
        .pix-box {
            margin-top: 6px;
            border: 1px solid #222;
            padding: 4px;
            font-size: 7px;
            word-break: break-all;
        }
        .pix-qr {
            width: 72px;
            height: 72px;
        }
    </style>
</head>
<body>
    {{-- Recibo do pagador --}}
    <div class="section-title">Recibo do pagador</div>
    <table class="hdr">
        <tr>
            <td style="width:120px;">
                @if ($logo_data_uri)
                    <img class="logo" src="{{ $logo_data_uri }}" alt="{{ $banco_nome }}">
                @else
                    <strong>{{ $banco_nome }}</strong>
                @endif
            </td>
            <td style="width:70px;" class="bank-code">{{ $banco }}-{{ $banco_dv }}</td>
            <td class="linha">{{ $linha }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top:4px;">
        <tr>
            <td colspan="5">
                <span class="lbl">Beneficiário</span>
                <span class="val val-sm">{{ $cedente_nome }} — {{ $cedente_doc }}</span>
            </td>
            <td style="width:22%;" class="right">
                <span class="lbl">Vencimento</span>
                <span class="val">{{ $vencimento }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="lbl">Agência / Código beneficiário</span>
                <span class="val">{{ $agencia_conta }}</span>
            </td>
            <td colspan="2">
                <span class="lbl">Nosso número</span>
                <span class="val">{{ $nosso_numero !== '' ? $nosso_numero : '—' }}</span>
            </td>
            <td colspan="2" class="right">
                <span class="lbl">Valor do documento</span>
                <span class="val">{{ $valor }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="lbl">Espécie</span>
                <span class="val">{{ $especie }}</span>
            </td>
            <td>
                <span class="lbl">Quantidade</span>
                <span class="val">—</span>
            </td>
            <td>
                <span class="lbl">Carteira</span>
                <span class="val">{{ $carteira !== '' ? $carteira : '—' }}</span>
            </td>
            <td>
                <span class="lbl">Espécie doc.</span>
                <span class="val">{{ $especie_doc }}</span>
            </td>
            <td>
                <span class="lbl">Nº documento</span>
                <span class="val">{{ $numero_documento !== '' ? $numero_documento : '—' }}</span>
            </td>
            <td class="right">
                <span class="lbl">(=) Valor cobrado</span>
                <span class="val">{{ $valor }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="6" class="sacado">
                <span class="lbl">Pagador</span>
                <span class="val val-sm">{{ $sacado_nome }} — {{ $sacado_doc }}</span>
                <span class="muted">{{ $sacado_endereco }}</span>
            </td>
        </tr>
    </table>

    <div class="cut">✂ Corte na linha pontilhada</div>

    {{-- Ficha de compensação --}}
    <table class="hdr">
        <tr>
            <td style="width:120px;">
                @if ($logo_data_uri)
                    <img class="logo" src="{{ $logo_data_uri }}" alt="{{ $banco_nome }}">
                @else
                    <strong>{{ $banco_nome }}</strong>
                @endif
            </td>
            <td style="width:70px;" class="bank-code">{{ $banco }}-{{ $banco_dv }}</td>
            <td class="linha">{{ $linha }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top:4px;">
        <tr>
            <td colspan="5">
                <span class="lbl">Local de pagamento</span>
                <span class="val val-sm">{{ $local_pagamento }}</span>
            </td>
            <td style="width:22%;" class="right">
                <span class="lbl">Vencimento</span>
                <span class="val">{{ $vencimento }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="5">
                <span class="lbl">Beneficiário</span>
                <span class="val val-sm">{{ $cedente_nome }} — CNPJ/CPF {{ $cedente_doc }}</span>
                @if ($cedente_endereco !== '')
                    <span class="muted">{{ $cedente_endereco }}</span>
                @endif
            </td>
            <td class="right">
                <span class="lbl">Agência / Código beneficiário</span>
                <span class="val">{{ $agencia_conta }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="lbl">Data do documento</span>
                <span class="val">{{ $emissao !== '' ? $emissao : '—' }}</span>
            </td>
            <td>
                <span class="lbl">Nº do documento</span>
                <span class="val">{{ $numero_documento !== '' ? $numero_documento : '—' }}</span>
            </td>
            <td>
                <span class="lbl">Espécie doc.</span>
                <span class="val">{{ $especie_doc }}</span>
            </td>
            <td>
                <span class="lbl">Aceite</span>
                <span class="val">{{ $aceite }}</span>
            </td>
            <td>
                <span class="lbl">Data processamento</span>
                <span class="val">{{ $processamento !== '' ? $processamento : '—' }}</span>
            </td>
            <td class="right">
                <span class="lbl">Nosso número</span>
                <span class="val">{{ $nosso_numero !== '' ? $nosso_numero : '—' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="lbl">Uso do banco</span>
                <span class="val">—</span>
            </td>
            <td>
                <span class="lbl">Carteira</span>
                <span class="val">{{ $carteira !== '' ? $carteira : '—' }}</span>
            </td>
            <td>
                <span class="lbl">Espécie</span>
                <span class="val">{{ $especie }}</span>
            </td>
            <td>
                <span class="lbl">Quantidade</span>
                <span class="val">—</span>
            </td>
            <td>
                <span class="lbl">Valor</span>
                <span class="val">—</span>
            </td>
            <td class="right">
                <span class="lbl">(=) Valor do documento</span>
                <span class="val">{{ $valor }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="5" rowspan="4" class="instr">
                <span class="lbl">Instruções (texto de responsabilidade do beneficiário)</span>
                @if (count($instrucoes) > 0)
                    <ul>
                        @foreach ($instrucoes as $linhaInstrucao)
                            <li>{{ $linhaInstrucao }}</li>
                        @endforeach
                    </ul>
                @else
                    <span class="muted">Não receber após o vencimento sem atualização de juros/multa.</span>
                @endif
            </td>
            <td class="right">
                <span class="lbl">(-) Desconto / Abatimento</span>
                <span class="val"> </span>
            </td>
        </tr>
        <tr>
            <td class="right">
                <span class="lbl">(-) Outras deduções</span>
                <span class="val"> </span>
            </td>
        </tr>
        <tr>
            <td class="right">
                <span class="lbl">(+) Mora / Multa</span>
                <span class="val"> </span>
            </td>
        </tr>
        <tr>
            <td class="right">
                <span class="lbl">(=) Valor cobrado</span>
                <span class="val"> </span>
            </td>
        </tr>
        <tr>
            <td colspan="6" class="sacado">
                <span class="lbl">Pagador</span>
                <span class="val val-sm">{{ $sacado_nome }} — {{ $sacado_doc }}</span>
                <span class="muted">{{ $sacado_endereco }}</span>
            </td>
        </tr>
    </table>

    @if ($barcode_data_uri)
        <div class="barcode">
            <img src="{{ $barcode_data_uri }}" alt="Código de barras">
        </div>
    @endif

    @if ($pix_copia_cola !== '' || $pix_qr_data_uri)
        <table style="margin-top:6px;">
            <tr>
                @if ($pix_qr_data_uri)
                    <td style="width:80px;vertical-align:top;">
                        <img class="pix-qr" src="{{ $pix_qr_data_uri }}" alt="QR Pix">
                    </td>
                @endif
                <td style="vertical-align:top;">
                    <div class="pix-box">
                        <strong>PIX Copia e Cola</strong><br>
                        {{ $pix_copia_cola !== '' ? $pix_copia_cola : '—' }}
                    </div>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
