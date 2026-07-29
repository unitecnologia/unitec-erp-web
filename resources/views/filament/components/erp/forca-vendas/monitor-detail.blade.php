@php
    $order = $this->selecionado;
    $itens = $this->itensSelecionado;
    $pagamentos = $this->pagamentosSelecionado;

    $fmtNum = fn (float $v): string => number_format($v, 2, ',', '.');
    $fmtQtd = fn (float $v): string => rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');

    $totItens = array_sum(array_map(fn ($i) => (float) $i['total'], $itens));
    $totPag = array_sum(array_map(fn ($p) => (float) $p['valor'], $pagamentos));
@endphp

<div class="erp-fv-mon__detail">
    <div class="erp-fv-mon__detail-grid">

        {{-- Itens do pedido --}}
        <section class="erp-fv-mon__panel erp-fv-mon__panel--itens">
            <div class="erp-fv-mon__table-wrap">
                <table class="erp-fv-mon__table">
                    <thead>
                        <tr>
                            <th class="erp-fv-mon__th--code">Código</th>
                            <th class="erp-fv-mon__th--code">Cód. Barras</th>
                            <th>Produto</th>
                            <th class="erp-fv-mon__th--num">Qtde</th>
                            <th class="erp-fv-mon__th--money">Vlr Unit.</th>
                            <th class="erp-fv-mon__th--money">Desc.</th>
                            <th class="erp-fv-mon__th--money">Acmo.</th>
                            <th class="erp-fv-mon__th--money">TT Líquido</th>
                            <th>Vendedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($itens as $item)
                            <tr>
                                <td class="erp-fv-mon__td--code">{{ $item['codigo'] ?: '—' }}</td>
                                <td class="erp-fv-mon__td--code">{{ $item['codigo_barras'] ?: '—' }}</td>
                                <td>{{ $item['descricao'] }}</td>
                                <td class="erp-fv-mon__td--num">{{ $fmtQtd($item['quantidade']) }}</td>
                                <td class="erp-fv-mon__td--money">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['preco_unitario']) }}</span>
                                    </span>
                                </td>
                                <td class="erp-fv-mon__td--money">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['desconto']) }}</span>
                                    </span>
                                </td>
                                <td class="erp-fv-mon__td--money">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['acrescimo']) }}</span>
                                    </span>
                                </td>
                                <td class="erp-fv-mon__td--money">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['total']) }}</span>
                                    </span>
                                </td>
                                <td>{{ $item['vendedor'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="erp-fv-mon__empty">
                                    {{ $order ? 'Pedido sem itens detalhados.' : 'Não há dados para mostrar' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="erp-fv-mon__foot">
                <span class="erp-fv-mon__foot-count">{{ count($itens) }}</span>
                <span class="erp-fv-mon__foot-total">
                    <span class="erp-fv-mon-money">
                        <span class="erp-fv-mon-money__currency">R$</span>
                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($totItens) }}</span>
                    </span>
                </span>
            </footer>
        </section>

        {{-- Pagamentos / Recebimentos --}}
        <section class="erp-fv-mon__panel erp-fv-mon__panel--lado">
            <div class="erp-fv-mon__table-wrap">
                <table class="erp-fv-mon__table">
                    <thead>
                        <tr>
                            <th>Meio Pgto</th>
                            <th class="erp-fv-mon__th--num">Parcela</th>
                            <th>Vencimento</th>
                            <th class="erp-fv-mon__th--money">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pagamentos as $pg)
                            <tr>
                                <td>{{ $pg['meio'] }}</td>
                                <td class="erp-fv-mon__td--num">{{ $pg['parcela'] }}</td>
                                <td>{{ $pg['vencimento'] }}</td>
                                <td class="erp-fv-mon__td--money">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($pg['valor']) }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="erp-fv-mon__empty">Não há dados para mostrar</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="erp-fv-mon__foot">
                <span class="erp-fv-mon__foot-count">{{ count($pagamentos) }}</span>
                <span class="erp-fv-mon__foot-total">
                    <span class="erp-fv-mon-money">
                        <span class="erp-fv-mon-money__currency">R$</span>
                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($totPag) }}</span>
                    </span>
                </span>
            </footer>
        </section>
    </div>
</div>
