@php
    $order = $this->selecionado;
    $itens = $this->itensSelecionado;
    $pagamentos = $this->pagamentosSelecionado;
    $exibirCustoGrade = $this->exibirCustoProdutoGrade;
    $custosGrade = $exibirCustoGrade ? $this->custosUnitariosGrade : [];
    $colspanItens = $exibirCustoGrade ? 11 : 10;

    $fmtNum = fn (float $v): string => number_format($v, 2, ',', '.');
    // Mesmo padrão da Tela de Venda: 3 casas (ex.: 1,000).
    $fmtQtd = fn (float $v): string => number_format($v, 3, ',', '.');

    $totItens = array_sum(array_map(fn ($i) => (float) $i['total'], $itens));
    // Soma das Qtde das linhas (já consolida agrupamentos na quantidade da linha).
    $totQtd = array_sum(array_map(fn ($i) => (float) ($i['quantidade'] ?? 0), $itens));
    $totPag = array_sum(array_map(fn ($p) => (float) $p['valor'], $pagamentos));
    $temItens = $itens !== [];
@endphp

<div class="erp-fv-mon__detail">
    <div class="erp-fv-mon__detail-grid">

        {{-- Itens do pedido --}}
        <section class="erp-fv-mon__panel erp-fv-mon__panel--itens">
            <div
                class="erp-fv-mon__table-wrap"
                data-erp-csort="fv-monitor-produtos"
                data-erp-csort-scope="{{ auth()->id() ?? 0 }}"
            >
                <table class="erp-fv-mon__table">
                    <thead>
                        <tr>
                            <th class="erp-fv-mon__th--item" data-erp-csort-key="item" data-erp-csort-type="number">#</th>
                            <th class="erp-fv-mon__th--code" data-erp-csort-key="codigo" data-erp-csort-type="text">Código</th>
                            <th class="erp-fv-mon__th--barras" data-erp-csort-key="codigo_barras" data-erp-csort-type="text">Cód. Barras</th>
                            <th data-erp-csort-key="produto" data-erp-csort-type="text">Produto</th>
                            <th class="erp-fv-mon__th--num" data-erp-csort-key="quantidade" data-erp-csort-type="number">Qtde</th>
                            <th class="erp-fv-mon__th--money erp-fv-mon__th--vlr" data-erp-csort-key="preco_unitario" data-erp-csort-type="money">Vlr Unit.</th>
                            @if ($exibirCustoGrade)
                                <th class="erp-fv-mon__th--money erp-fv-mon__th--custo" data-erp-csort-key="custo" data-erp-csort-type="money">Custo</th>
                            @endif
                            <th class="erp-fv-mon__th--money erp-fv-mon__th--desc" data-erp-csort-key="desconto" data-erp-csort-type="money">Desc.</th>
                            <th class="erp-fv-mon__th--money erp-fv-mon__th--acre" data-erp-csort-key="acrescimo" data-erp-csort-type="money">Acre.</th>
                            <th class="erp-fv-mon__th--money erp-fv-mon__th--liq" data-erp-csort-key="total" data-erp-csort-type="money">TT Líquido</th>
                            <th data-erp-csort-key="vendedor" data-erp-csort-type="text">Vendedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($itens as $idx => $item)
                            <tr>
                                <td class="erp-fv-mon__td--item">{{ $idx + 1 }}</td>
                                <td class="erp-fv-mon__td--code">{{ $item['codigo'] ?: '—' }}</td>
                                <td class="erp-fv-mon__td--barras">{{ $item['codigo_barras'] ?: '—' }}</td>
                                <td>{{ $item['descricao'] }}</td>
                                <td class="erp-fv-mon__td--num">{{ $fmtQtd($item['quantidade']) }}</td>
                                <td class="erp-fv-mon__td--money erp-fv-mon__td--vlr">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['preco_unitario']) }}</span>
                                    </span>
                                </td>
                                @if ($exibirCustoGrade)
                                    <td class="erp-fv-mon__td--money erp-fv-mon__td--custo">
                                        <span class="erp-fv-mon-money">
                                            <span class="erp-fv-mon-money__currency">R$</span>
                                            <span class="erp-fv-mon-money__amount">{{ $fmtNum((float) ($custosGrade[(int) ($item['product_id'] ?? 0)] ?? 0)) }}</span>
                                        </span>
                                    </td>
                                @endif
                                <td class="erp-fv-mon__td--money erp-fv-mon__td--desc">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['desconto']) }}</span>
                                    </span>
                                </td>
                                <td class="erp-fv-mon__td--money erp-fv-mon__td--acre">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['acrescimo']) }}</span>
                                    </span>
                                </td>
                                <td class="erp-fv-mon__td--money erp-fv-mon__td--liq">
                                    <span class="erp-fv-mon-money">
                                        <span class="erp-fv-mon-money__currency">R$</span>
                                        <span class="erp-fv-mon-money__amount">{{ $fmtNum($item['total']) }}</span>
                                    </span>
                                </td>
                                <td>{{ $item['vendedor'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $colspanItens }}" class="erp-fv-mon__empty">
                                    {{ $order ? 'Pedido sem itens detalhados.' : 'Não há dados para mostrar' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="erp-fv-mon__foot erp-fv-mon__foot--itens" data-erp-fv-mon-foot-itens>
                @if ($temItens)
                    <span
                        class="erp-fv-mon__foot-count erp-fv-mon__foot-qtd-sum"
                        data-erp-fv-mon-qtd-sum
                        title="Soma das quantidades"
                    >{{ $fmtQtd($totQtd) }}</span>
                @endif
                <span class="erp-fv-mon__foot-end">
                    @if ((bool) (\App\Support\Erp\ErpContext::currentEmpresa()?->param_monitor_vendas_exibir_mais_opcoes ?? false))
                        <span class="erp-fv-mon__foot-mais">
                            @include('filament.components.erp.forca-vendas.mais-opcoes-menu', [
                                'enabled' => count($this->selecionados) === 1,
                                'onMargem' => 'abrirMargemVenda',
                            ])
                        </span>
                    @endif
                    @if ($order)
                        <span class="erp-fv-mon__foot-pedido">
                            PEDIDO:
                            {{ \App\Filament\Resources\ForcaVendasMonitorResource::formatNumeroVenda($order) }}
                        </span>
                        <span class="erp-fv-mon__foot-pedido">
                            DAV:
                            {{ \App\Filament\Resources\ForcaVendasMonitorResource::formatNumeroDav($order) }}
                        </span>
                    @endif
                    <span class="erp-fv-mon__foot-total">
                        <span class="erp-fv-mon-money">
                            <span class="erp-fv-mon-money__currency">R$</span>
                            <span class="erp-fv-mon-money__amount">{{ $fmtNum($totItens) }}</span>
                        </span>
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

@once
    <script>
        (function () {
            if (window.__erpFvMonQtdSumAlignBound) {
                return;
            }
            window.__erpFvMonQtdSumAlignBound = true;

            function alignFvMonQtdSum() {
                document.querySelectorAll('.erp-fv-mon__panel--itens').forEach((panel) => {
                    const foot = panel.querySelector('[data-erp-fv-mon-foot-itens]');
                    const sum = panel.querySelector('[data-erp-fv-mon-qtd-sum]');
                    const th = panel.querySelector('thead th[data-erp-csort-key="quantidade"]');
                    if (! foot || ! sum || ! th) {
                        return;
                    }
                    const footRect = foot.getBoundingClientRect();
                    const thRect = th.getBoundingClientRect();
                    const left = Math.max(0, thRect.left - footRect.left);
                    sum.style.left = left + 'px';
                    sum.style.width = Math.max(0, thRect.width) + 'px';
                });
            }

            window.__erpFvMonAlignQtdSum = alignFvMonQtdSum;

            document.addEventListener('DOMContentLoaded', alignFvMonQtdSum);
            window.addEventListener('resize', alignFvMonQtdSum);
            document.addEventListener('livewire:navigated', alignFvMonQtdSum);
            document.addEventListener('livewire:init', () => {
                Livewire.hook('morph.updated', () => {
                    window.requestAnimationFrame(alignFvMonQtdSum);
                });
            });

            document.addEventListener('scroll', (event) => {
                if (event.target?.closest?.('.erp-fv-mon__panel--itens .erp-fv-mon__table-wrap')) {
                    alignFvMonQtdSum();
                }
            }, true);

            window.setTimeout(alignFvMonQtdSum, 50);
            window.setTimeout(alignFvMonQtdSum, 250);
        })();
    </script>
@endonce
