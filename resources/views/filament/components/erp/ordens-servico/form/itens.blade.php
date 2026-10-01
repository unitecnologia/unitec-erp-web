@php
    $readOnly = $readOnly ?? false;
    $itensTab = $this->itensByActiveTab();
    $atendentes = $this->atendenteOptions();
    $showFoto = $this->activeItemTab === 'pecas';
    $colCount = ($readOnly ? 11 : 12);
    $tabTotal = count($itensTab);
    $tecnicoNome = collect($atendentes)->firstWhere('id', $this->atendenteId)['nome'] ?? '—';
@endphp

<div class="erp-os-itens erp-os-itens--fv">
    @unless ($readOnly)
        <section @class([
            'erp-fv-tv__panel erp-fv-tv__panel--produto erp-orc-produto-bar erp-os-produto-bar',
            'erp-os-produto-bar--editing' => $this->editingItemIndex !== null,
        ])>
            <div class="erp-fv-tv__box">
                <span class="erp-fv-tv__box-legend">
                    {{ $this->activeItemTab === 'servicos' ? 'Serviço' : 'Peça' }}
                </span>

                <div class="erp-fv-tv__row erp-fv-tv__row--produto">
                    <label class="erp-fv-tv__field erp-fv-tv__field--barcode erp-fv-tv__field--suggest">
                        <span>Código / barras / nome</span>
                        <div class="erp-fv-tv__barcode-wrap erp-os-produto-field erp-orc-produto-field">
                            <input
                                id="os-item-descricao"
                                class="erp-nfe__input erp-fv-tv__input--barcode"
                                type="text"
                                wire:model.live.debounce.200ms="itemProdutoSearch"
                                wire:focus="openProdutoLookup"
                                wire:keydown.enter.prevent="confirmarItemProdutoBar($event.target.value)"
                                wire:keydown.escape.prevent="closeProdutoLookup"
                                wire:keydown.arrow-up.prevent="moveProdutoSelection(-1)"
                                wire:keydown.arrow-down.prevent="moveProdutoSelection(1)"
                                data-erp-os-prod-suggest="1"
                                @disabled($readOnly)
                                data-erp-uppercase
                                autocomplete="off"
                                placeholder="Código, barras ou nome do produto — Enter"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->produtoLookupOpen && $this->produtoResults !== [] ? 'true' : 'false' }}"
                            >
                            @if ($this->produtoLookupOpen && $this->produtoResults !== [])
                                <ul @class([
                                    'erp-fv-tv__suggest erp-fv-tv__suggest--produto',
                                    'erp-os-suggest--pecas' => $this->activeItemTab === 'pecas',
                                ]) role="listbox" aria-label="Produtos encontrados">
                                    @foreach ($this->produtoResults as $index => $sug)
                                        <li wire:key="os-prod-sug-{{ $sug['id'] }}" role="presentation">
                                            <button
                                                type="button"
                                                role="option"
                                                aria-selected="{{ $this->selectedProdutoIndex === $index ? 'true' : 'false' }}"
                                                wire:mousedown.prevent="selectProdutoResult({{ $index }})"
                                                @class([
                                                    'erp-os-suggest-btn',
                                                    'is-selected' => $this->selectedProdutoIndex === $index,
                                                ])
                                            >
                                                <span class="erp-fv-tv__suggest-code">{{ $sug['codigo'] }}</span>
                                                <span class="erp-os-suggest-main">
                                                    <span class="erp-fv-tv__suggest-nome">{{ $sug['descricao'] }}</span>
                                                    @if ($this->activeItemTab === 'pecas')
                                                        <span class="erp-os-suggest-meta">
                                                            <span class="erp-os-suggest-meta__item">EAN {{ $sug['codigo_barras'] ?? '—' }}</span>
                                                            <span class="erp-os-suggest-meta__item">IMEI {{ $sug['imei_resumo'] ?? '—' }}</span>
                                                        </span>
                                                    @endif
                                                </span>
                                                @if ($this->activeItemTab === 'pecas')
                                                    <span class="erp-fv-tv__suggest-estoques">
                                                        <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--atual">Atual {{ $sug['atual'] ?? '0,000' }}</span>
                                                        <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--reservado">Res {{ $sug['reservado'] ?? '0,000' }}</span>
                                                        <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--disponivel">Disp {{ $sug['disponivel'] ?? '0,000' }}</span>
                                                    </span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($this->produtoLookupOpen && filled($this->itemProdutoSearch))
                                <div class="erp-orc-produto-lookup erp-orc-produto-lookup--empty">
                                    Nenhum produto encontrado.
                                </div>
                            @endif
                        </div>
                    </label>

                    <label class="erp-fv-tv__field erp-fv-tv__field--qtd">
                        <span>Qtde</span>
                        <input
                            id="os-item-qtd"
                            class="erp-nfe__input"
                            type="text"
                            wire:model="itemQtdInput"
                            wire:blur="normalizeItemQtdInput"
                            wire:keydown.enter.prevent="focoPrecoAposQtd"
                            @disabled($this->itemPendingProductId === null && $this->editingItemIndex === null)
                            inputmode="decimal"
                            autocomplete="off"
                        >
                    </label>

                    <div class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--preco">
                        <span>Vlr. unit.</span>
                        <div class="erp-fv-tv__preco-wrap">
                            <input
                                id="os-item-preco"
                                class="erp-nfe__input"
                                type="text"
                                wire:model="itemPrecoInput"
                                wire:keydown.enter.prevent="confirmPendingItemEntry"
                                @disabled($this->itemPendingProductId === null && $this->editingItemIndex === null)
                                data-mask="money"
                                inputmode="decimal"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                class="erp-fv-tv__btn-desc"
                                wire:click="abrirModalDescontoItem"
                                @disabled($this->itemPendingProductId === null && $this->editingItemIndex === null)
                                title="Desconto / Acréscimo (Ctrl+D)"
                            >%</button>
                        </div>
                    </div>

                    <label class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--total-item">
                        <span>Total item</span>
                        <input
                            id="os-item-total"
                            class="erp-nfe__input erp-fv-tv__input--total"
                            type="text"
                            value="{{ $this->itemPendingProductId !== null || $this->editingItemIndex !== null ? $this->itemTotalEntryDisplay : '0,00' }}"
                            readonly
                            tabindex="-1"
                        >
                    </label>
                </div>
            </div>
        </section>
    @endunless

    <div @class(['erp-fv-tv__body', 'erp-os-itens__body', 'erp-os-itens__body--no-foto' => ! $showFoto])>
        <div class="erp-fv-tv__grid-wrap">
            <table class="erp-fv-tv__grid erp-os-itens__grid-fv">
                <thead>
                    <tr>
                        @unless ($readOnly)
                            <th class="erp-fv-tv__col-idx" aria-label="Excluir"></th>
                        @endunless
                        <th class="erp-fv-tv__col-idx">#</th>
                        <th class="erp-fv-tv__col-cod">Código</th>
                        <th class="erp-os-itens__col-descricao">Descrição</th>
                        <th class="erp-fv-tv__col-num">Qtde</th>
                        <th class="erp-fv-tv__col-num">Vlr. unit.</th>
                        <th class="erp-fv-tv__col-num">TT bruto</th>
                        <th class="erp-fv-tv__col-num">Acrés.</th>
                        <th class="erp-fv-tv__col-num">Desc.</th>
                        <th class="erp-fv-tv__col-num">TT líq.</th>
                        <th class="erp-os-itens__col-tecnico">Técnico</th>
                        <th class="erp-os-itens__col-concluido">Concluído em</th>
                    </tr>
                </thead>
                <tbody>
                    @php $posInTab = 0; @endphp
                    @forelse ($itensTab as $index => $item)
                        @php
                            $lineNum = $this->resolveItemDisplayNumberInTab($posInTab, $tabTotal);
                            $posInTab++;
                            $qtdVal = \App\Support\Erp\ErpMoney::parseBr($item['qtd'] ?? 0, 3);
                            $precoVal = \App\Support\Erp\ErpMoney::parseBr($item['preco'] ?? 0);
                            $bruto = round($qtdVal * $precoVal, 2);
                            $acrVal = \App\Support\Erp\ErpMoney::parseBr($item['acrescimo'] ?? 0);
                            $descVal = \App\Support\Erp\ErpMoney::parseBr($item['desconto'] ?? 0);
                            $liqVal = \App\Support\Erp\ErpMoney::parseBr($item['total'] ?? ($bruto + $acrVal - $descVal));
                        @endphp
                        <tr
                            wire:key="{{ $item['key'] ?? ('os-item-' . $index) }}"
                            wire:click="selectItemRow({{ $index }})"
                            @unless ($readOnly)
                                wire:dblclick.stop="startEditItem({{ $index }})"
                            @endunless
                            @class([
                                'is-selected' => $this->selectedItemIndex === $index,
                                'is-editing' => $this->editingItemIndex === $index,
                            ])
                        >
                            @unless ($readOnly)
                                <td class="erp-fv-tv__col-idx">
                                    <button
                                        type="button"
                                        class="erp-os-itens__trash-btn"
                                        wire:click.stop="requestDeleteItem({{ $index }})"
                                        title="Excluir item"
                                        aria-label="Excluir item"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M4 7h16"/>
                                            <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                            <path d="M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/>
                                            <path d="M10 11v6M14 11v6"/>
                                        </svg>
                                    </button>
                                </td>
                            @endunless
                            <td class="erp-fv-tv__col-idx">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $lineNum }}</div>
                            </td>
                            <td class="erp-fv-tv__col-cod">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $item['product_codigo'] ?? '' }}</div>
                            </td>
                            <td class="erp-os-itens__col-descricao">
                                <div class="erp-os-itens__desc-cell">
                                    <div
                                        class="erp-fv-tv__cell erp-fv-tv__cell--desc"
                                        title="{{ $item['discriminacao'] ?? '' }}"
                                    >{{ $item['discriminacao'] ?? '' }}</div>
                                    @if ($this->activeItemTab === 'servicos' && ! $readOnly)
                                        <button
                                            type="button"
                                            class="erp-os-itens__desc-edit"
                                            wire:click.stop="abrirModalServicoPrestado"
                                            title="Serviços prestados (impressão / app)"
                                            aria-label="Editar serviços prestados"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3z"/>
                                                <path d="M13.5 6.5l3 3"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--num">{{ $item['qtd'] ?? '' }}</div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ $item['preco'] ?? '0,00' }}</span>
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
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($acrVal) }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money erp-fv-tv__val-desc">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($descVal) }}</span>
                                </div>
                            </td>
                            <td class="erp-fv-tv__col-num erp-fv-tv__val-liq">
                                <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                    <span class="erp-fv-tv__money-rs">R$</span>
                                    <span class="erp-fv-tv__money-val">{{ \App\Support\Erp\ErpMoney::formatBr($liqVal) }}</span>
                                </div>
                            </td>
                            <td class="erp-os-itens__col-tecnico">
                                <div class="erp-fv-tv__cell erp-os-itens__cell-tecnico" title="{{ $tecnicoNome }}">{{ $tecnicoNome }}</div>
                            </td>
                            <td class="erp-os-itens__col-concluido">
                                @if ($readOnly)
                                    <div class="erp-fv-tv__cell erp-os-itens__cell-concluido">{{ filled($item['concluido_em'] ?? null) ? \Illuminate\Support\Str::replace('T', ' ', $item['concluido_em']) : '—' }}</div>
                                @else
                                    <input
                                        type="datetime-local"
                                        wire:key="os-item-{{ $item['key'] }}-concl"
                                        value="{{ $item['concluido_em'] ?? '' }}"
                                        @change="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'concluido_em', $event.target.value)"
                                        wire:click.stop
                                        class="erp-fv-tv__cell erp-os-itens__cell-input--concluido"
                                    >
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="erp-fv-tv__empty">
                            <td colspan="{{ $colCount }}">Nenhum item nesta aba — informe o código e pressione Enter</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($showFoto)
            <aside class="erp-fv-tv__aside">
                <div class="erp-fv-tv__foto">
                    @if ($this->produtoAtualFoto)
                        <img
                            src="{{ $this->produtoAtualFoto }}"
                            alt="{{ $this->produtoAtualNome }}"
                            onerror="this.style.display='none'; const ph=this.parentElement.querySelector('.erp-fv-tv__foto-empty'); if(ph){ ph.hidden=false; }"
                        >
                        <div class="erp-fv-tv__foto-empty" hidden>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <circle cx="8.5" cy="10.5" r="1.5"/>
                                <path d="M21 16l-5-5-4 4-2-2-5 5"/>
                            </svg>
                            <span>Foto do produto</span>
                        </div>
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
            </aside>
        @endif
    </div>
</div>
