@php
    $m = fn ($v) => \App\Support\Erp\ErpMoney::formatBr((float) $v);
    $flags = $flags ?? [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Fechamento Livro Caixa — Mov. {{ $movimentoId }} — {{ $emitente['fantasia'] ?: $emitente['nome'] }}</title>
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
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .box { border: 1px solid #222; margin-bottom: 3mm; }
        .box--keep { page-break-inside: avoid; }
        .box-title {
            padding: 1.2mm 2mm;
            border-bottom: 1px solid #222;
            background: #efefef;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .box-title small { float: right; font-weight: normal; text-transform: none; color: #444; }
        .muted { color: #444; }
        .num { text-align: right; white-space: nowrap; }
        .in { color: #166534; }
        .out { color: #b91c1c; }
        .emit td { padding: 2mm; vertical-align: middle; }
        .emit__logo { width: 30mm; text-align: center; }
        .emit__logo img { max-width: 28mm; max-height: 20mm; }
        .emit__nome { font-size: 12.5px; font-weight: bold; }
        .emit__doc { width: 52mm; text-align: center; border-left: 1px solid #222; }
        .emit__titulo { font-size: 12px; font-weight: bold; letter-spacing: 0.03em; }
        .situacao {
            display: inline-block;
            margin-top: 1.5mm;
            padding: 0.8mm 3mm;
            border: 1.5px solid currentColor;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.08em;
            color: #374151;
        }
        .campos td { padding: 1.4mm 2mm; border-bottom: 1px solid #ddd; }
        .campos tr:last-child td { border-bottom: 0; }
        .campos td + td { border-left: 1px solid #ddd; }
        .rotulo { display: block; font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: #333; }
        .valor { display: block; font-size: 10px; }
        .valor--destaque { font-size: 12px; font-weight: bold; }
        .grade th {
            padding: 1.2mm 2mm;
            border-bottom: 1px solid #222;
            font-size: 7.5px;
            text-align: left;
            text-transform: uppercase;
            color: #333;
        }
        .grade th.num { text-align: right; }
        .grade td { padding: 1.1mm 2mm; border-bottom: 1px solid #e5e5e5; }
        .grade tbody tr:nth-child(even) td { background: #fafafa; }
        .grade tfoot td { padding: 1.3mm 2mm; border-top: 1px solid #222; font-weight: bold; background: #f5f5f5; }
        .grade .vazio { padding: 2mm; text-align: center; color: #555; }
        .aviso {
            padding: 2mm;
            border: 1px dashed #999;
            background: #fafafa;
            color: #444;
            font-size: 8.5px;
            margin: 0;
        }
        .assinaturas { margin-top: 10mm; page-break-inside: avoid; }
        .assinaturas td { width: 50%; padding: 0 8mm; text-align: center; font-size: 8.5px; }
        .assinaturas .linha { border-top: 1px solid #111; padding-top: 1mm; }
        .rodape { margin-top: 3mm; font-size: 8px; text-align: center; color: #444; }
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
        .toolbar button.toolbar__fechar {
            border-color: #94a3b8;
            background: #fff;
            color: #334155;
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
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        <button
            type="button"
            class="toolbar__fechar"
            onclick="
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'erp-caixa-fechamento-preview-close' }, '*');
                } else {
                    window.close();
                }
            "
        >Fechar</button>
    </div>

    <div class="sheet">
        <div class="box box--keep">
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
                        <div>CNPJ: {{ $emitente['cnpj'] ?: '—' }}@if (($emitente['ie'] ?? '') !== '') &nbsp;&nbsp; IE: {{ $emitente['ie'] }}@endif</div>
                        @if (($emitente['endereco'] ?? '') !== '')
                            <div>{{ $emitente['endereco'] }}</div>
                        @endif
                        <div>{{ $emitente['municipio'] }}@if (($emitente['uf'] ?? '') !== '') — {{ $emitente['uf'] }}@endif @if (($emitente['telefone'] ?? '') !== '') &nbsp;&nbsp; Fone: {{ $emitente['telefone'] }}@endif</div>
                    </td>
                    <td class="emit__doc">
                        <div class="emit__titulo">FECHAMENTO DE CAIXA</div>
                        <div class="muted">Livro Caixa · Mov. nº {{ $movimentoId }}</div>
                        <div class="situacao">{{ $status }}</div>
                    </td>
                </tr>
            </table>
        </div>

        @if (! empty($flags['operadores']))
            <div class="box box--keep">
                <table class="campos">
                    <tr>
                        <td style="width: 28%;"><span class="rotulo">Conta</span><span class="valor">{{ $contaNome }}</span></td>
                        <td style="width: 24%;"><span class="rotulo">Aberto por</span><span class="valor">{{ $abertoPor }}</span></td>
                        <td style="width: 24%;"><span class="rotulo">Fechado por</span><span class="valor">{{ $fechadoPor }}</span></td>
                        <td><span class="rotulo">Troco inicial</span><span class="valor">R$ {{ $m($trocoInicial) }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="rotulo">Abertura</span><span class="valor">{{ $abertoEm }}</span></td>
                        <td><span class="rotulo">Fechamento</span><span class="valor">{{ $fechadoEm }}</span></td>
                        <td><span class="rotulo">Status</span><span class="valor">{{ $status }}</span></td>
                        <td><span class="rotulo">Impresso em</span><span class="valor">{{ $impressoEm }}</span></td>
                    </tr>
                    @if (filled($observacao ?? null))
                        <tr>
                            <td colspan="4"><span class="rotulo">Observações</span><span class="valor">{{ $observacao }}</span></td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        @if (! empty($flags['resumo']))
            <div class="box box--keep">
                <div class="box-title">Resumo financeiro <small>Troco operacional ≠ saldo financeiro</small></div>
                <table class="campos">
                    <tr>
                        <td style="width: 25%;"><span class="rotulo">Saldo financeiro (abertura)</span><span class="valor">R$ {{ $m($saldoAberturaFin) }}</span></td>
                        <td style="width: 25%;"><span class="rotulo">Entradas</span><span class="valor in">R$ {{ $m($totalEntradas) }}</span></td>
                        <td style="width: 25%;"><span class="rotulo">Saídas</span><span class="valor out">R$ {{ $m($totalSaidas) }}</span></td>
                        <td><span class="rotulo">Saldo financeiro atual</span><span class="valor valor--destaque">R$ {{ $m($saldoFinanceiroAtual) }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="rotulo">Transferências (entrada)</span><span class="valor">R$ {{ $m($trfEntrada) }}</span></td>
                        <td><span class="rotulo">Transferências (saída)</span><span class="valor">R$ {{ $m($trfSaida) }}</span></td>
                        <td><span class="rotulo">Saldo operacional (movimento)</span><span class="valor valor--destaque">R$ {{ $m($saldoMovimento) }}</span></td>
                        <td><span class="rotulo">Total informado</span><span class="valor valor--destaque">R$ {{ $m($totalInformado) }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="rotulo">Valor contado (físico)</span><span class="valor">R$ {{ $m($valorContado) }}</span></td>
                        <td colspan="3">
                            <span class="rotulo">Diferença (físico)</span>
                            <span class="valor valor--destaque {{ $diferenca !== null && abs((float) $diferenca) >= 0.01 ? ($diferenca > 0 ? 'in' : 'out') : '' }}">
                                @if ($diferenca !== null)
                                    R$ {{ $m($diferenca) }}
                                @else
                                    —
                                @endif
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        @if (! empty($flags['formas']))
            <div class="box">
                <div class="box-title">Conferência por forma de pagamento</div>
                <table class="grade">
                    <thead>
                        <tr>
                            <th>Forma</th>
                            <th class="num" style="width: 18%;">Esperado</th>
                            <th class="num" style="width: 18%;">Informado</th>
                            <th class="num" style="width: 18%;">Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($formas as $f)
                            <tr>
                                <td>
                                    {{ $f['label'] ?? $f['chave'] ?? '—' }}
                                    @if (! empty($f['destino_outra_conta']))
                                        <span class="muted"> · outra conta</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if (! empty($f['esperado_disponivel']))
                                        {{ $m($f['esperado'] ?? 0) }}
                                    @else
                                        <span class="muted">Não apurável</span>
                                    @endif
                                </td>
                                <td class="num">{{ $m($f['informado'] ?? 0) }}</td>
                                <td class="num">
                                    @if (($f['diferenca'] ?? null) !== null)
                                        {{ $m($f['diferenca']) }}
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="vazio">Nenhuma conferência por forma registrada neste fechamento.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($formas !== [])
                        <tfoot>
                            <tr>
                                <td>Totais</td>
                                <td class="num">—</td>
                                <td class="num">{{ $m($totaisFormas['informado'] ?? $totalInformado) }}</td>
                                <td class="num">
                                    @if ($diferenca !== null)
                                        {{ $m($diferenca) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif

        @if (! empty($flags['entradas_saidas']))
            <div class="box">
                <div class="box-title">Entradas e saídas (exceto transferências)</div>
                <table class="grade">
                    <thead>
                        <tr>
                            <th style="width: 14%;">Emissão</th>
                            <th>Histórico</th>
                            <th style="width: 16%;">Documento</th>
                            <th class="num" style="width: 14%;">Entrada</th>
                            <th class="num" style="width: 14%;">Saída</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($detalheEntradasSaidas as $row)
                            <tr>
                                <td>{{ $row['emissao'] }}</td>
                                <td>{{ $row['historico'] }}</td>
                                <td>{{ $row['documento'] }}</td>
                                <td class="num in">{{ $m($row['entrada']) }}</td>
                                <td class="num out">{{ $m($row['saida']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="vazio">Nenhum lançamento operacional no período deste movimento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if (! empty($flags['transferencias']))
            <div class="box">
                <div class="box-title">Transferências entre contas</div>
                <table class="grade">
                    <thead>
                        <tr>
                            <th style="width: 14%;">Emissão</th>
                            <th>Histórico</th>
                            <th style="width: 18%;">Documento</th>
                            <th class="num" style="width: 14%;">Entrada</th>
                            <th class="num" style="width: 14%;">Saída</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($detalheTransferencias as $row)
                            <tr>
                                <td>{{ $row['emissao'] }}</td>
                                <td>{{ $row['historico'] }}</td>
                                <td>{{ $row['documento'] }}</td>
                                <td class="num in">{{ $m($row['entrada']) }}</td>
                                <td class="num out">{{ $m($row['saida']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="vazio">Nenhuma transferência (TRF-) neste movimento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if (! empty($flags['vendas']))
            <div class="box">
                <div class="box-title">Vendas / recebimentos vinculados</div>
                @if ($avisoVendas)
                    <p class="aviso">{{ $avisoVendas }}</p>
                @else
                    <table class="grade">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Emissão</th>
                                <th style="width: 22%;">Origem</th>
                                <th style="width: 18%;">Documento</th>
                                <th>Cliente</th>
                                <th class="num" style="width: 14%;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detalheVendas as $row)
                                <tr>
                                    <td>{{ $row['emissao'] }}</td>
                                    <td>{{ $row['origem'] }}</td>
                                    <td>{{ $row['documento'] }}</td>
                                    <td>{{ $row['cliente'] }}</td>
                                    <td class="num">{{ $m($row['valor']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        @if (! empty($flags['clientes']))
            <div class="box">
                <div class="box-title">Clientes</div>
                @if ($avisoClientes)
                    <p class="aviso">{{ $avisoClientes }}</p>
                @else
                    <table class="grade">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th style="width: 30%;">Origem</th>
                                <th class="num" style="width: 16%;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detalheClientes as $row)
                                <tr>
                                    <td>{{ $row['cliente'] }}</td>
                                    <td>{{ $row['origem'] }}</td>
                                    <td class="num">{{ $m($row['valor']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        <table class="assinaturas">
            <tr>
                <td><div class="linha">Responsável pela abertura</div></td>
                <td><div class="linha">Responsável pelo fechamento</div></td>
            </tr>
        </table>

        <div class="rodape">
            UNI SISTEMAS 3.0 · Fechamento Livro Caixa · Mov. {{ $movimentoId }} · impresso em {{ $impressoEm }}
        </div>
    </div>

    @if ($autoPrint ?? false)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
