<div class="erp-orc-itens">
    <p class="erp-orc-itens__hint">Clique no <strong>✕</strong> ou na tecla [DEL] para excluir item</p>

    <div class="erp-orc-itens__grid-wrap">
        <table class="erp-orc-itens__grid">
            <thead>
                <tr>
                    @unless ($readOnly)
                        <th class="erp-orc-itens__col-delete" aria-label="Excluir"></th>
                    @endunless
                    <th class="erp-orc-itens__col-item">Item</th>
                    <th>Cód.</th>
                    <th>Pesquisar por Código ou Descrição</th>
                    <th>Quant.</th>
                    <th>Un.</th>
                    <th>Preço</th>
                    <th>Total</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                @unless ($readOnly)
                    <tr @class([
                        'erp-orc-itens__row',
                        'erp-orc-itens__row--entry',
                        'erp-orc-itens__row--entry-pending' => $this->itemPendingProductId !== null,
                    ])>
                        @unless ($readOnly)
                            <td class="erp-orc-itens__col-delete"></td>
                        @endunless
                        <td class="erp-orc-itens__col-item"></td>
                        <td>
                            <input
                                id="orc-item-codigo"
                                type="text"
                                wire:model="itemCodigoInput"
                                wire:keydown.enter.prevent="handleItemCodigoEnter"
                                @readonly($this->itemPendingProductId !== null)
                                class="erp-orc-itens__cell-input erp-orc-itens__cell-input--codigo"
                                autocomplete="off"
                            >
                        </td>
                        <td>
                            <div class="erp-orc-produto-field">
                                <input
                                    id="orc-item-descricao"
                                    type="text"
                                    wire:model="itemProdutoSearch"
                                    wire:focus="openProdutoLookup"
                                    wire:keydown.arrow-up.prevent="moveProdutoSelection(-1)"
                                    wire:keydown.arrow-down.prevent="moveProdutoSelection(1)"
                                    wire:keydown.enter.prevent="submitItemProdutoSearch($event.target.value)"
                                    wire:keydown.escape.prevent="closeProdutoLookup"
                                    @input.debounce.300ms="$wire.searchItemProduto($event.target.value)"
                                    @readonly($this->itemPendingProductId !== null)
                                    class="erp-orc-itens__cell-input erp-orc-itens__cell-input--descricao"
                                    data-erp-uppercase
                                    autocomplete="off"
                                    placeholder="Pesquisar por código ou descrição"
                                    wire:key="orc-item-descricao-input"
                                >
                                @if ($this->produtoLookupOpen && filled($this->itemProdutoSearch))
                                    @if ($this->produtoResults !== [])
                                        @include('filament.components.erp.orcamentos.form.produto-lookup')
                                    @else
                                        <div class="erp-orc-produto-lookup erp-orc-produto-lookup--empty">
                                            Nenhum produto encontrado.
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($this->itemPendingProductId)
                                <input
                                    id="orc-item-quantidade"
                                    type="text"
                                    wire:model.live.debounce.200ms="itemQuantidadeInput"
                                    wire:keydown.enter.prevent="handleItemQuantidadeEnter"
                                    class="erp-orc-itens__cell-input erp-orc-itens__cell-input--num"
                                    autocomplete="off"
                                >
                            @else
                                <span class="erp-orc-itens__entry-muted">1,000</span>
                            @endif
                        </td>
                        <td>
                            @if ($this->itemPendingProductId)
                                {{ $this->itemUnidadeDisplay }}
                            @else
                                <span class="erp-orc-itens__entry-muted">—</span>
                            @endif
                        </td>
                        <td class="erp-orc-itens__cell-input--num">
                            @if ($this->itemPendingProductId)
                                @if ($this->itemPendingPrecoVariavel)
                                    <input
                                        id="orc-item-preco"
                                        type="text"
                                        wire:model.live.debounce.200ms="itemPrecoInput"
                                        wire:keydown.enter.prevent="confirmPendingItemEntry"
                                        class="erp-orc-itens__cell-input erp-orc-itens__cell-input--num"
                                        data-mask="money"
                                        autocomplete="off"
                                    >
                                @else
                                    {{ $this->itemPrecoDisplay }}
                                @endif
                            @else
                                <span class="erp-orc-itens__entry-muted">—</span>
                            @endif
                        </td>
                        <td class="erp-orc-itens__cell-input--num">
                            @if ($this->itemPendingProductId)
                                {{ $this->itemTotalEntryDisplay }}
                            @else
                                <span class="erp-orc-itens__entry-muted">—</span>
                            @endif
                        </td>
                        <td class="erp-orc-itens__entry-muted">—</td>
                    </tr>
                @endunless

                @forelse ($this->itens as $index => $item)
                    <tr
                        wire:key="{{ $item['key'] ?? ('orc-item-' . $index) }}"
                        @click="$wire.selectItemRow({{ $index }})"
                        @class(['erp-orc-itens__row', 'erp-orc-itens__row--selected' => $this->selectedItemIndex === $index])
                        @dblclick.prevent="$wire.openProdutoFromItem({{ $index }})"
                    >
                        @unless ($readOnly)
                            <td class="erp-orc-itens__col-delete">
                                <button
                                    type="button"
                                    class="erp-orc-itens__delete-btn"
                                    @click.stop.prevent="$wire.requestDeleteItem({{ $index }})"
                                    title="Excluir item"
                                    aria-label="Excluir item"
                                >✕</button>
                            </td>
                        @endunless
                        <td class="erp-orc-itens__col-item">{{ $this->resolveItemDisplayNumber($index) }}</td>
                        <td>{{ $item['product_codigo'] ?? '' }}</td>
                        <td>
                            @if ($readOnly)
                                {{ $item['descricao'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="orc-item-{{ $item['key'] }}-desc"
                                    value="{{ $item['descricao'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'descricao', $event.target.value)"
                                    wire:click.stop
                                    class="erp-orc-itens__cell-input"
                                >
                            @endif
                        </td>
                        <td>
                            @if ($readOnly)
                                {{ $item['quantidade'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="orc-item-{{ $item['key'] }}-qtd"
                                    value="{{ $item['quantidade'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'quantidade', $event.target.value)"
                                    wire:click.stop
                                    class="erp-orc-itens__cell-input erp-orc-itens__cell-input--num"
                                >
                            @endif
                        </td>
                        <td>{{ $item['unidade'] ?? '' }}</td>
                        <td>
                            @if ($readOnly || ! ($item['preco_variavel'] ?? false))
                                {{ $item['preco_unitario'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="orc-item-{{ $item['key'] }}-preco"
                                    value="{{ $item['preco_unitario'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'preco_unitario', $event.target.value)"
                                    wire:click.stop
                                    class="erp-orc-itens__cell-input erp-orc-itens__cell-input--num"
                                    data-mask="money"
                                >
                            @endif
                        </td>
                        <td>{{ $item['total'] ?? '' }}</td>
                        <td>{{ $item['grade_descricao'] ?? '' }}</td>
                    </tr>
                @empty
                    @if ($readOnly)
                        <tr>
                            <td colspan="8" class="erp-orc-itens__empty">Nenhum item informado.</td>
                        </tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="erp-orc-itens__barcode">
        <label class="erp-orc-itens__barcode-label" for="orc-barcode">
            <kbd>F11</kbd> | Passe o Código de Barras para Adicionar Item
        </label>
        <input
            id="orc-barcode"
            type="text"
            wire:model="barcodeInput"
            wire:keydown.enter.prevent="submitBarcodeItem"
            @disabled($readOnly)
            class="erp-orc-itens__barcode-input"
            autocomplete="off"
        >
    </div>
</div>
