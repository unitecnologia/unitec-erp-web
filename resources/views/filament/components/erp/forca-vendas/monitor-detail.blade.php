@php
    $order = $this->selecionado;
    $itens = $this->itensSelecionado;
    $pagamentos = $this->pagamentosSelecionado;

    $fmt = fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
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
                            <th class="erp-fv-mon__th--num">Vlr Unit.</th>
                            <th class="erp-fv-mon__th--num">Desc.</th>
                            <th class="erp-fv-mon__th--num">TT Líquido</th>
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
                                <td class="erp-fv-mon__td--num">{{ $fmt($item['preco_unitario']) }}</td>
                                <td class="erp-fv-mon__td--num">{{ $fmt($item['desconto']) }}</td>
                                <td class="erp-fv-mon__td--num">{{ $fmt($item['total']) }}</td>
                                <td>{{ $item['vendedor'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="erp-fv-mon__empty">
                                    {{ $order ? 'Pedido sem itens detalhados.' : 'Não há dados para mostrar' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="erp-fv-mon__foot">
                <span class="erp-fv-mon__foot-count">{{ count($itens) }}</span>
                <span class="erp-fv-mon__foot-total">{{ $fmt($totItens) }}</span>
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
                            <th class="erp-fv-mon__th--num">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pagamentos as $pg)
                            <tr>
                                <td>{{ $pg['meio'] }}</td>
                                <td class="erp-fv-mon__td--num">{{ $pg['parcela'] }}</td>
                                <td>{{ $pg['vencimento'] }}</td>
                                <td class="erp-fv-mon__td--num">{{ $fmt($pg['valor']) }}</td>
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
                <span class="erp-fv-mon__foot-total">{{ $fmt($totPag) }}</span>
            </footer>
        </section>
    </div>
</div>
