@php
    $readOnly = $readOnly ?? $this->orcamentoReadOnly();
@endphp

<div class="erp-orc-itens erp-orc-itens--fv">
    <div class="erp-fv-tv__body">
        <div class="erp-fv-tv__grid-wrap">
            <table class="erp-fv-tv__grid">
                <thead>
                    <tr>
                        @unless ($readOnly)
                            <th class="erp-fv-tv__col-idx" aria-label="Excluir"></th>
                        @endunless
                        <th class="erp-fv-tv__col-idx">#</th>
                        <th class="erp-fv-tv__col-cod">Código</th>
                        <th>Produto</th>
                        <th class="erp-fv-tv__col-num">Qtde</th>
                        <th class="erp-fv-tv__col-num">Vlr. unit.</th>
                        <th class="erp-fv-tv__col-num">TT bruto</th>
                        <th class="erp-fv-tv__col-num">Acrés.</th>
                        <th class="erp-fv-tv__col-num">Desc.</th>
                        <th class="erp-fv-tv__col-num">TT líq.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->itens as $index => $item)
                        @php
                            $qtd = \App\Support\Erp\ErpMoney::parseBr($item['quantidade'] ?? 0, 3);
                            $preco = \App\Support\Erp\ErpMoney::parseBr($item['preco_unitario'] ?? 0);
                            $bruto = round($qtd * $preco, 2);
                            $acr = \App\Support\Erp\ErpMoney::parseBr($item['acrescimo'] ?? 0);
                            $desc = \App\Support\Erp\ErpMoney::parseBr($item['desconto'] ?? 0);
                            $liq = \App\Support\Erp\ErpMoney::parseBr($item['total'] ?? ($bruto + $acr - $desc));
                        @endphp
                        <tr
                            wire:key="{{ $item['key'] ?? ('orc-item-' . $index) }}"
                            wire:click="selectItemRow({{ $index }})"
                            @class(['is-selected' => $this->selectedItemIndex === $index])
                        >
                            @unless ($readOnly)
                                <td class="erp-fv-tv__col-idx">
                                    <button
                                        type="button"
                                        class="erp-orc-itens__delete-btn"
                                        wire:click.stop="requestDeleteItem({{ $index }})"
                                        title="Excluir item"
                                        aria-label="Excluir item"
                                    >✕</button>
                                </td>
                            @endunless
                            <td class="erp-fv-tv__col-idx">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $this->resolveItemDisplayNumber($index) }}</div>
                            </td>
                            <td class="erp-fv-tv__col-cod">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $item['product_codigo'] ?? '' }}</div>
                            </td>
                            <td>
                                <div
                                    class="erp-fv-tv__cell erp-fv-tv__cell--desc"
                                    title="{{ $item['descricao'] ?? '' }}"
                                >{{ $item['descricao'] ?? '' }}</div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--num">{{ $item['quantidade'] ?? '' }}</div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ $item['preco_unitario'] ?? '0,00' }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($bruto) }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money erp-fv-tv__val-acr">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($acr) }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money erp-fv-tv__val-desc">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($desc) }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num erp-fv-tv__val-liq">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($liq) }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="erp-fv-tv__empty">
                            <td colspan="{{ $readOnly ? 9 : 10 }}">Nenhum item — informe o código e pressione Enter</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <aside class="erp-fv-tv__aside">
            <div class="erp-fv-tv__foto">
                @if ($this->produtoAtualFoto)
                    <img src="{{ $this->produtoAtualFoto }}" alt="{{ $this->produtoAtualNome }}">
                @else
                    <div class="erp-fv-tv__foto-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <circle cx="8.5" cy="10.5" r="1.5"/>
                            <path d="M21 16l-5-5-4 4-2-2-5 5"/>
                        </svg>
                        <span>Foto do produto</span>
                    </div>
                @endif
                @if ($this->produtoAtualNome !== '')
                    <p class="erp-fv-tv__foto-caption">{{ $this->produtoAtualNome }}</p>
                @endif
            </div>

            <div class="erp-fv-tv__totais">
                <div class="erp-fv-tv__totais-head">Resumo</div>
                <div class="erp-fv-tv__total-row">
                    <span>Total bruto</span>
                    <strong>{{ $this->itensResumoBrutoDisplay() }}</strong>
                </div>
                <div class="erp-fv-tv__total-row">
                    <span>Acréscimos</span>
                    <strong class="erp-fv-tv__val-acr">{{ $this->itensResumoAcrescimosDisplay() }}</strong>
                </div>
                <div class="erp-fv-tv__total-row">
                    <span>Descontos</span>
                    <strong class="erp-fv-tv__val-desc">{{ $this->itensResumoDescontosDisplay() }}</strong>
                </div>
                <div class="erp-fv-tv__total-row erp-fv-tv__total-row--liq">
                    <span>Total líquido</span>
                    <strong>R$ {{ $this->itensResumoLiquidoDisplay() }}</strong>
                </div>
                <div class="erp-fv-tv__itens-count">
                    {{ count($this->itens) }} {{ count($this->itens) === 1 ? 'item' : 'itens' }}
                </div>
            </div>
        </aside>
    </div>
</div>
