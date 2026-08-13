@php
    $itensTab = $this->itensByActiveTab();
    $atendentes = $this->atendenteOptions();
@endphp

<div class="erp-os-itens">
    <p class="erp-os-itens__hint">
        Clique no <strong>✕</strong> ou use <kbd>CTRL</kbd>+<kbd>DEL</kbd> para excluir um item da grade
    </p>

    <div class="erp-os-itens__grid-wrap">
        <table class="erp-os-itens__grid">
            <thead>
                <tr>
                    @unless ($readOnly)
                        <th class="erp-os-itens__col-delete" aria-label="Excluir"></th>
                    @endunless
                    <th>Cód.</th>
                    <th>Pesquisar por Código ou Descrição</th>
                    <th>Qtd.</th>
                    <th>Preço</th>
                    <th>Total</th>
                    <th>Técnico</th>
                    <th>Concluído Em</th>
                </tr>
            </thead>
            <tbody>
                @unless ($readOnly)
                    <tr @class([
                        'erp-os-itens__row',
                        'erp-os-itens__row--entry',
                        'erp-os-itens__row--entry-pending' => $this->itemPendingProductId !== null,
                    ])>
                        <td class="erp-os-itens__col-delete"></td>
                        <td>
                            <input
                                id="os-item-codigo"
                                type="text"
                                wire:model="itemCodigoInput"
                                wire:keydown.enter.prevent="handleItemCodigoEnter"
                                @readonly($this->itemPendingProductId !== null)
                                class="erp-os-itens__cell-input erp-os-itens__cell-input--codigo"
                                autocomplete="off"
                            >
                        </td>
                        <td>
                            <div class="erp-os-produto-field erp-orc-produto-field">
                                <input
                                    id="os-item-descricao"
                                    type="text"
                                    wire:model="itemProdutoSearch"
                                    wire:focus="openProdutoLookup"
                                    wire:keydown.arrow-up.prevent="moveProdutoSelection(-1)"
                                    wire:keydown.arrow-down.prevent="moveProdutoSelection(1)"
                                    wire:keydown.enter.prevent="submitItemProdutoSearch($event.target.value)"
                                    wire:keydown.escape.prevent="closeProdutoLookup"
                                    @input.debounce.300ms="$wire.searchItemProduto($event.target.value)"
                                    @readonly($this->itemPendingProductId !== null)
                                    class="erp-os-itens__cell-input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                    placeholder="Pesquisar por código ou descrição"
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
                                    id="os-item-qtd"
                                    type="text"
                                    wire:model="itemQtdInput"
                                    wire:keydown.enter.prevent="confirmPendingItemEntry"
                                    class="erp-os-itens__cell-input erp-os-itens__cell-input--num"
                                    autocomplete="off"
                                >
                            @else
                                <span class="erp-os-itens__entry-muted">1,000</span>
                            @endif
                        </td>
                        <td>
                            @if ($this->itemPendingProductId)
                                <input
                                    id="os-item-preco"
                                    type="text"
                                    wire:model="itemPrecoInput"
                                    wire:keydown.enter.prevent="confirmPendingItemEntry"
                                    class="erp-os-itens__cell-input erp-os-itens__cell-input--num"
                                    data-mask="money"
                                    autocomplete="off"
                                >
                            @else
                                <span class="erp-os-itens__entry-muted">—</span>
                            @endif
                        </td>
                        <td class="erp-os-itens__entry-muted">—</td>
                        <td class="erp-os-itens__entry-muted">—</td>
                        <td class="erp-os-itens__entry-muted">—</td>
                    </tr>
                @endunless

                @forelse ($itensTab as $index => $item)
                    <tr
                        wire:key="{{ $item['key'] ?? ('os-item-' . $index) }}"
                        @click="$wire.selectItemRow({{ $index }})"
                        @class(['erp-os-itens__row', 'erp-os-itens__row--selected' => $this->selectedItemIndex === $index])
                    >
                        @unless ($readOnly)
                            <td class="erp-os-itens__col-delete">
                                <button
                                    type="button"
                                    class="erp-os-itens__delete-btn"
                                    @click.stop.prevent="$wire.requestDeleteItem({{ $index }})"
                                    title="Excluir item"
                                    aria-label="Excluir item"
                                >✕</button>
                            </td>
                        @endunless
                        <td>{{ $item['product_codigo'] ?? '' }}</td>
                        <td>
                            @if ($readOnly)
                                {{ $item['discriminacao'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="os-item-{{ $item['key'] }}-desc"
                                    value="{{ $item['discriminacao'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'discriminacao', $event.target.value)"
                                    wire:click.stop
                                    class="erp-os-itens__cell-input"
                                >
                            @endif
                        </td>
                        <td>
                            @if ($readOnly)
                                {{ $item['qtd'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="os-item-{{ $item['key'] }}-qtd"
                                    value="{{ $item['qtd'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'qtd', $event.target.value)"
                                    wire:click.stop
                                    class="erp-os-itens__cell-input erp-os-itens__cell-input--num"
                                >
                            @endif
                        </td>
                        <td>
                            @if ($readOnly)
                                {{ $item['preco'] ?? '' }}
                            @else
                                <input
                                    type="text"
                                    wire:key="os-item-{{ $item['key'] }}-preco"
                                    value="{{ $item['preco'] ?? '' }}"
                                    @blur="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'preco', $event.target.value)"
                                    wire:click.stop
                                    class="erp-os-itens__cell-input erp-os-itens__cell-input--num"
                                    data-mask="money"
                                >
                            @endif
                        </td>
                        <td style="text-align:right; font-weight:700;">{{ $item['total'] ?? '' }}</td>
                        <td>
                            @if ($readOnly)
                                @php
                                    $tecNome = collect($atendentes)->firstWhere('id', $item['funcionario_id'] ?? null)['nome'] ?? '—';
                                @endphp
                                {{ $tecNome }}
                            @else
                                <select
                                    wire:key="os-item-{{ $item['key'] }}-tec"
                                    @change="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'funcionario_id', $event.target.value)"
                                    wire:click.stop
                                    class="erp-os-itens__cell-input"
                                >
                                    <option value="">—</option>
                                    @foreach ($atendentes as $atendente)
                                        <option
                                            value="{{ $atendente['id'] }}"
                                            @selected((int) ($item['funcionario_id'] ?? 0) === (int) $atendente['id'])
                                        >{{ $atendente['nome'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td>
                            @if ($readOnly)
                                {{ filled($item['concluido_em'] ?? null) ? \Illuminate\Support\Str::replace('T', ' ', $item['concluido_em']) : '—' }}
                            @else
                                <input
                                    type="datetime-local"
                                    wire:key="os-item-{{ $item['key'] }}-concl"
                                    value="{{ $item['concluido_em'] ?? '' }}"
                                    @change="$wire.blurItemFieldByKey('{{ $item['key'] }}', 'concluido_em', $event.target.value)"
                                    wire:click.stop
                                    class="erp-os-itens__cell-input"
                                >
                            @endif
                        </td>
                    </tr>
                @empty
                    @if ($readOnly)
                        <tr>
                            <td colspan="8" class="erp-os-itens__empty">Nenhum item nesta aba.</td>
                        </tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="erp-os-itens__barcode">
        <label class="erp-os-itens__barcode-label" for="os-barcode">
            <kbd>F11</kbd> | Passe o Código de Barras para Adicionar Item
        </label>
        <input
            id="os-barcode"
            type="text"
            wire:model="barcodeInput"
            wire:keydown.enter.prevent="submitBarcodeItem"
            @disabled($readOnly)
            class="erp-os-itens__barcode-input"
            autocomplete="off"
        >
    </div>
</div>
