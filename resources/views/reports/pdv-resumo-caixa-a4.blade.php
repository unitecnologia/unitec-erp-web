@php
    $m = fn ($v) => \App\Support\Erp\ErpMoney::formatBr((float) $v);
    $sangrias = array_values(array_filter($operacoes, fn ($o) => $o['tipo'] === 'sangria'));
    $suprimentos = array_values(array_filter($operacoes, fn ($o) => $o['tipo'] === 'suprimento'));
    $formasEntrada = array_sum(array_column($formas, 'entrada'));
    $formasSaida = array_sum(array_column($formas, 'saida'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Resumo de Caixa — Sessão {{ $sessaoId }} — {{ $emitente['fantasia'] ?: $emitente['nome'] }}</title>
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
        .emit__titulo { font-size: 13px; font-weight: bold; letter-spacing: 0.03em; }
        .situacao {
            display: inline-block;
            margin-top: 1.5mm;
            padding: 0.8mm 3mm;
            border: 1.5px solid currentColor;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.08em;
        }
        .situacao--aberto { color: #166534; }
        .situacao--fechado { color: #374151; }

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

        .duas { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 3mm; }
        .duas > tbody > tr > td { width: 50%; padding: 0; }
        .duas > tbody > tr > td:first-child { padding-right: 1.5mm; }
        .duas > tbody > tr > td:last-child { padding-left: 1.5mm; }
        .duas .box { margin-bottom: 0; height: 100%; }

        .resumo td { padding: 1.3mm 2mm; border-bottom: 1px solid #e5e5e5; }
        .resumo tr:last-child td { border-bottom: 0; }
        .resumo .total td { border-top: 1px solid #222; font-weight: bold; font-size: 10.5px; background: #f5f5f5; }
        .diferenca--sobra { color: #166534; }
        .diferenca--falta { color: #b91c1c; }

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
                        <div class="emit__titulo">RESUMO DE CAIXA</div>
                        <div class="muted">PDV · Sessão nº {{ $sessaoId }}</div>
                        <div class="situacao {{ $caixaAberto ? 'situacao--aberto' : 'situacao--fechado' }}">{{ $caixaAberto ? 'ABERTO' : 'FECHADO' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="box box--keep">
            <table class="campos">
                <tr>
                    <td style="width: 28%;"><span class="rotulo">Operador</span><span class="valor">{{ $operador }}</span></td>
                    <td style="width: 24%;"><span class="rotulo">Usuário</span><span class="valor">{{ $usuario }}</span></td>
                    <td style="width: 24%;"><span class="rotulo">Terminal</span><span class="valor">{{ $terminal }}</span></td>
                    <td><span class="rotulo">Fundo de troco</span><span class="valor">R$ {{ $m($abertura) }}</span></td>
                </tr>
                <tr>
                    <td><span class="rotulo">Abertura</span><span class="valor">{{ $abertoEm }}</span></td>
                    <td><span class="rotulo">Fechamento</span><span class="valor">{{ $caixaAberto ? 'Caixa em aberto' : ($fechadoEm ?? '—') }}</span></td>
                    <td><span class="rotulo">Situação</span><span class="valor">{{ $caixaAberto ? 'ABERTO' : 'FECHADO' }}</span></td>
                    <td><span class="rotulo">Impresso em</span><span class="valor">{{ $impressoEm }}</span></td>
                </tr>
            </table>
        </div>

        <div class="box box--keep">
            <table class="campos">
                <tr>
                    <td style="width: 25%;"><span class="rotulo">Total de entradas</span><span class="valor valor--destaque in">R$ {{ $m($totalEntrada) }}</span></td>
                    <td style="width: 25%;"><span class="rotulo">Total de saídas</span><span class="valor valor--destaque out">R$ {{ $m($totalSaida) }}</span></td>
                    <td style="width: 25%;"><span class="rotulo">Saldo total</span><span class="valor valor--destaque">R$ {{ $m($saldoTotal) }}</span></td>
                    <td><span class="rotulo">Saldo em dinheiro</span><span class="valor valor--destaque">R$ {{ $m($saldoDinheiro) }}</span></td>
                </tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">Saldos por forma de pagamento <small>Saídas = estornos de vendas canceladas</small></div>
            <table class="grade">
                <thead>
                    <tr>
                        <th>Forma de pagamento</th>
                        <th class="num" style="width: 20%;">Entradas</th>
                        <th class="num" style="width: 20%;">Saídas</th>
                        <th class="num" style="width: 20%;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($formas as $f)
                        <tr>
                            <td>{{ $f['forma'] }}</td>
                            <td class="num">{{ $m($f['entrada']) }}</td>
                            <td class="num">{{ $m($f['saida']) }}</td>
                            <td class="num"><strong>{{ $m($f['saldo']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="vazio">Nenhuma venda nesta sessão.</td></tr>
                    @endforelse
                </tbody>
                @if ($formas !== [])
                    <tfoot>
                        <tr>
                            <td>Total das vendas</td>
                            <td class="num">{{ $m($formasEntrada) }}</td>
                            <td class="num">{{ $m($formasSaida) }}</td>
                            <td class="num">{{ $m($formasEntrada - $formasSaida) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <table class="duas">
            <tr>
                <td>
                    <div class="box">
                        <div class="box-title">Sangrias <small>{{ count($sangrias) }} lançamento(s)</small></div>
                        <table class="grade">
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Data/hora</th>
                                    <th>Histórico</th>
                                    <th class="num" style="width: 24%;">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sangrias as $o)
                                    <tr>
                                        <td>{{ $o['hora'] }}</td>
                                        <td>{{ $o['historico'] }}@if ($o['forma'] !== 'DINHEIRO') <span class="muted">({{ $o['forma'] }})</span>@endif</td>
                                        <td class="num out">{{ $m($o['valor']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="vazio">Nenhuma sangria.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($sangrias !== [])
                                <tfoot><tr><td colspan="2">Total</td><td class="num">{{ $m($totalSangria) }}</td></tr></tfoot>
                            @endif
                        </table>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <div class="box-title">Suprimentos <small>{{ count($suprimentos) }} lançamento(s)</small></div>
                        <table class="grade">
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Data/hora</th>
                                    <th>Histórico</th>
                                    <th class="num" style="width: 24%;">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suprimentos as $o)
                                    <tr>
                                        <td>{{ $o['hora'] }}</td>
                                        <td>{{ $o['historico'] }}@if ($o['forma'] !== 'DINHEIRO') <span class="muted">({{ $o['forma'] }})</span>@endif</td>
                                        <td class="num in">{{ $m($o['valor']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="vazio">Nenhum suprimento.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($suprimentos !== [])
                                <tfoot><tr><td colspan="2">Total</td><td class="num">{{ $m($totalSuprimento) }}</td></tr></tfoot>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="duas box--keep">
            <tr>
                <td>
                    <div class="box">
                        <div class="box-title">Movimentação geral do caixa</div>
                        <table class="resumo">
                            <tr><td>Fundo de troco (abertura)</td><td class="num">{{ $m($abertura) }}</td></tr>
                            <tr><td>Vendas (todas as formas)</td><td class="num in">{{ $m($formasEntrada) }}</td></tr>
                            <tr><td>Estornos de vendas canceladas</td><td class="num out">− {{ $m($formasSaida) }}</td></tr>
                            <tr><td>Suprimentos</td><td class="num in">{{ $m($totalSuprimento) }}</td></tr>
                            <tr><td>Sangrias</td><td class="num out">− {{ $m($totalSangria) }}</td></tr>
                            <tr class="total"><td>Saldo total</td><td class="num">R$ {{ $m($saldoTotal) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <div class="box-title">Conferência de dinheiro</div>
                        <table class="resumo">
                            <tr><td>Dinheiro esperado (sistema)</td><td class="num">{{ $m($saldoDinheiro) }}</td></tr>
                            <tr><td>Dinheiro informado (contado)</td><td class="num">{{ $dinheiroInformado !== null ? $m($dinheiroInformado) : 'Não informado' }}</td></tr>
                            @php
                                $dif = $diferencaDinheiro;
                                $difClasse = $dif === null || abs($dif) < 0.005 ? '' : ($dif > 0 ? 'diferenca--sobra' : 'diferenca--falta');
                                $difTexto = $dif === null ? '—' : (abs($dif) < 0.005 ? 'Sem diferença' : ($dif > 0 ? 'Sobra' : 'Falta'));
                            @endphp
                            <tr><td>Situação</td><td class="num {{ $difClasse }}">{{ $difTexto }}</td></tr>
                            <tr class="total"><td>Diferença (informado − sistema)</td><td class="num {{ $difClasse }}">{{ $dif !== null ? 'R$ '.$m($dif) : '—' }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="box">
            <div class="box-title">Vendas canceladas <small>{{ count($vendasCanceladas) }} venda(s)</small></div>
            <table class="grade">
                <thead>
                    <tr>
                        <th style="width: 10%;">Venda</th>
                        <th style="width: 13%;">NFC-e / Série</th>
                        <th style="width: 17%;">Cancelada em</th>
                        <th>Motivo</th>
                        <th class="num" style="width: 14%;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendasCanceladas as $v)
                        <tr>
                            <td>#{{ $v['numero'] }}</td>
                            <td>{{ $v['nfce'] }}</td>
                            <td>{{ $v['em'] }}</td>
                            <td>{{ $v['motivo'] }}</td>
                            <td class="num">{{ $m($v['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="vazio">Nenhuma venda cancelada.</td></tr>
                    @endforelse
                </tbody>
                @if ($vendasCanceladas !== [])
                    <tfoot><tr><td colspan="4">Total cancelado</td><td class="num">{{ $m($totalVendasCanceladas) }}</td></tr></tfoot>
                @endif
            </table>
        </div>

        @if ($produtosCancelados !== [])
            <div class="box">
                <div class="box-title">Produtos cancelados no cupom <small>{{ count($produtosCancelados) }} item(ns)</small></div>
                <table class="grade">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Código</th>
                            <th>Descrição</th>
                            <th class="num" style="width: 10%;">Qtd</th>
                            <th style="width: 14%;">Data/hora</th>
                            <th class="num" style="width: 14%;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($produtosCancelados as $p)
                            <tr>
                                <td>{{ $p['codigo'] ?: '—' }}</td>
                                <td>{{ $p['descricao'] ?: '—' }}</td>
                                <td class="num">{{ \App\Support\Erp\ErpMoney::formatBr($p['qtd'], 3) }}</td>
                                <td>{{ $p['em'] }}</td>
                                <td class="num">{{ $m($p['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="4">Total</td><td class="num">{{ $m($totalProdutosCancelados) }}</td></tr></tfoot>
                </table>
            </div>
        @endif

        <table class="assinaturas">
            <tr>
                <td><div class="linha">{{ $operador }}<br><span class="muted">Operador</span></div></td>
                <td><div class="linha">&nbsp;<br><span class="muted">Conferente</span></div></td>
            </tr>
        </table>

        <div class="rodape">
            @if ($caixaAberto)
                Resumo parcial — caixa ainda aberto; valores sujeitos a alteração até o fechamento. &nbsp;·&nbsp;
            @endif
            Impresso em {{ $impressoEm }} &nbsp;·&nbsp; DESENVOLVIDO POR UNITECNOLOGIA SISTEMAS LTDA
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
