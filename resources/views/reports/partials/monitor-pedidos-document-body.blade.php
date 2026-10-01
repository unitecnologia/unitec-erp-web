@php
    use App\Support\Erp\Reports\MonitorPedidosReport;

    $blocos = $blocos ?? [];
    $impSemColunaDesconto = filter_var($impSemColunaDesconto ?? true, FILTER_VALIDATE_BOOLEAN);
    $colSpanVazio = $impSemColunaDesconto ? 6 : 7;
@endphp

<div class="monitor-pedidos-doc">
    <table class="monitor-pedidos-doc__pages">
        <tbody>
            @forelse ($blocos as $bloco)
                @php
                    $itens = array_values($bloco['itens'] ?? []);
                    $primeiroItem = $itens[0] ?? null;
                    $demaisItens = array_slice($itens, 1);
                    $tableClass = 'monitor-pedidos-doc__table'.($impSemColunaDesconto ? '' : ' monitor-pedidos-doc__table--com-desconto');
                @endphp
                <tr>
                    <td>
                        <div class="monitor-pedidos-doc__block">
                            <div class="monitor-pedidos-doc__keep-start">
                                <div class="monitor-pedidos-doc__meta-wrap">
                                    @if (filled($bloco['status_label'] ?? null))
                                        <span class="monitor-pedidos-doc__status monitor-pedidos-doc__status--{{ $bloco['status_key'] ?? 'pendente' }}">
                                            {{ mb_strtoupper((string) $bloco['status_label'], 'UTF-8') }}
                                        </span>
                                    @endif
                                    <table class="monitor-pedidos-doc__meta">
                                    <tr>
                                        <td>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Cliente:</span>
                                                {{ $bloco['cliente'] }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Fantasia:</span>
                                                {{ $bloco['fantasia'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Endereço:</span>
                                                {{ $bloco['endereco'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Município:</span>
                                                {{ $bloco['municipio'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Repres:</span>
                                                {{ $bloco['repres'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Obs:</span>
                                                {{ $bloco['obs'] ?: '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">CNPJ:</span>
                                                {{ $bloco['documento'] ?: '' }}
                                                &nbsp;<span class="monitor-pedidos-doc__meta-label">IE:</span>
                                                {{ $bloco['ie'] ?: '' }}
                                            </div>
                                            @if (filled($bloco['carga'] ?? null))
                                                <div class="monitor-pedidos-doc__meta-line">
                                                    <span class="monitor-pedidos-doc__meta-label">Carga:</span>
                                                    {{ $bloco['carga'] }}
                                                </div>
                                            @endif
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Pedido:</span>
                                                @php
                                                    $pedidoRaw = trim((string) ($bloco['pedido'] ?? ''));
                                                    $pedidoSep = strpos($pedidoRaw, ' - ');
                                                @endphp
                                                @if ($pedidoSep !== false)
                                                    <strong class="monitor-pedidos-doc__pedido-num">{{ substr($pedidoRaw, 0, $pedidoSep) }}</strong>{{ substr($pedidoRaw, $pedidoSep) }}
                                                @else
                                                    <strong class="monitor-pedidos-doc__pedido-num">{{ $pedidoRaw }}</strong>
                                                @endif
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Cond. Pagamento:</span>
                                                {{ $bloco['condicao_pagamento'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Bairro:</span>
                                                {{ $bloco['bairro'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">CEP:</span>
                                                {{ $bloco['cep'] ?: '' }}
                                                &nbsp;&nbsp;
                                                <span class="monitor-pedidos-doc__meta-label">UF:</span>
                                                {{ $bloco['uf'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Fone:</span>
                                                {{ $bloco['fone'] ?: '' }}
                                            </div>
                                            <div class="monitor-pedidos-doc__meta-line">
                                                <span class="monitor-pedidos-doc__meta-label">Obs Pedido:</span>
                                                {{ $bloco['obs_pedido'] ?: '' }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                </div>

                                <table class="{{ $tableClass }}">
                                    @include('reports.partials.monitor-pedidos-document-colgroup', ['impSemColunaDesconto' => $impSemColunaDesconto])
                                    <tr class="monitor-pedidos-doc__table-head">
                                        <th class="col-codigo">Código</th>
                                        <th class="col-produto">Produto</th>
                                        <th class="col-un">UN</th>
                                        <th class="col-qtd">Qtd</th>
                                        <th class="col-unit">Val. Unitário</th>
                                        @unless ($impSemColunaDesconto)
                                            <th class="col-desc">Desconto</th>
                                        @endunless
                                        <th class="col-sub">Subtotal</th>
                                    </tr>
                                    @if ($primeiroItem !== null)
                                        <tr>
                                            <td class="col-codigo">{{ $primeiroItem['codigo'] }}</td>
                                            <td class="produto">{{ mb_strtoupper($primeiroItem['produto'], 'UTF-8') }}</td>
                                            <td class="col-un">{{ $primeiroItem['unidade'] }}</td>
                                            <td class="col-qtd">{{ MonitorPedidosReport::formatQuantidade((float) $primeiroItem['quantidade']) }}</td>
                                            <td class="col-unit">{{ MonitorPedidosReport::formatMoney((float) $primeiroItem['valor_unitario']) }}</td>
                                            @unless ($impSemColunaDesconto)
                                                <td class="col-desc">{{ MonitorPedidosReport::formatMoney((float) ($primeiroItem['desconto'] ?? 0)) }}</td>
                                            @endunless
                                            <td class="col-sub">{{ MonitorPedidosReport::formatMoney((float) $primeiroItem['subtotal']) }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="{{ $colSpanVazio }}">Nenhum item.</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>

                            @if (count($demaisItens) > 0)
                                <table class="{{ $tableClass }} monitor-pedidos-doc__table--continue">
                                    @include('reports.partials.monitor-pedidos-document-colgroup', ['impSemColunaDesconto' => $impSemColunaDesconto])
                                    @foreach ($demaisItens as $item)
                                        <tr>
                                            <td class="col-codigo">{{ $item['codigo'] }}</td>
                                            <td class="produto">{{ mb_strtoupper($item['produto'], 'UTF-8') }}</td>
                                            <td class="col-un">{{ $item['unidade'] }}</td>
                                            <td class="col-qtd">{{ MonitorPedidosReport::formatQuantidade((float) $item['quantidade']) }}</td>
                                            <td class="col-unit">{{ MonitorPedidosReport::formatMoney((float) $item['valor_unitario']) }}</td>
                                            @unless ($impSemColunaDesconto)
                                                <td class="col-desc">{{ MonitorPedidosReport::formatMoney((float) ($item['desconto'] ?? 0)) }}</td>
                                            @endunless
                                            <td class="col-sub">{{ MonitorPedidosReport::formatMoney((float) $item['subtotal']) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <div class="monitor-pedidos-doc__keep-end">
                                <table class="{{ $tableClass }} monitor-pedidos-doc__table--continue">
                                    @include('reports.partials.monitor-pedidos-document-colgroup', ['impSemColunaDesconto' => $impSemColunaDesconto])
                                    <tr class="monitor-pedidos-doc__totals-row">
                                        <td></td>
                                        <td class="totais-label">Totais:</td>
                                        <td></td>
                                        <td class="col-qtd">{{ MonitorPedidosReport::formatQuantidade((float) $bloco['qtd_total']) }}</td>
                                        <td></td>
                                        @unless ($impSemColunaDesconto)
                                            <td class="col-desc">{{ MonitorPedidosReport::formatMoney((float) array_sum(array_column($bloco['itens'] ?? [], 'desconto'))) }}</td>
                                        @endunless
                                        <td class="col-sub">{{ MonitorPedidosReport::formatMoney((float) $bloco['valor_total']) }}</td>
                                    </tr>
                                </table>

                                <div class="monitor-pedidos-doc__aviso">
                                    RECLAMAÇÕES REFERENTES AOS ITENS DESSE PEDIDO SOMENTE NO ATO DA ENTREGA (FAVOR CONFERIR ITEM A ITEM)
                                </div>

                                <div class="monitor-pedidos-doc__rodape">
                                    <span class="monitor-pedidos-doc__impresso">
                                        IMPRESSO POR {{ mb_strtoupper((string) ($printedBy ?? '—'), 'UTF-8') }}
                                        — {{ ($printedAt ?? now())->format('d/m/Y H:i:s') }}
                                    </span>
                                    <span class="monitor-pedidos-doc__credito">
                                        Desenvolvido Por Unitecnologia Sistemas LTDA
                                    </span>
                                </div>
                                @unless (! empty($isPdf))
                                    <div class="monitor-pedidos-doc__footer-page">Pag: 1 de 1</div>
                                @endunless

                                @if (! $loop->last)
                                    <div class="monitor-pedidos-doc__cut" aria-hidden="true">
                                        <span class="monitor-pedidos-doc__cut-icon">✂</span>
                                        <span class="monitor-pedidos-doc__cut-line"></span>
                                        <span class="monitor-pedidos-doc__cut-label">corte</span>
                                        <span class="monitor-pedidos-doc__cut-line"></span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td><p>Nenhum pedido para imprimir.</p></td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
