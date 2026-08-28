@php
    $readOnly = $readOnly ?? $this->orcamentoReadOnly();
@endphp

<section class="erp-fv-tv__panel erp-fv-tv__panel--produto erp-orc-produto-bar">
    <div class="erp-fv-tv__box">
        <span class="erp-fv-tv__box-legend">Produto</span>

        <div class="erp-fv-tv__row erp-fv-tv__row--produto">
            <label class="erp-fv-tv__field erp-fv-tv__field--barcode erp-fv-tv__field--suggest">
                <span>Código / barras / nome</span>
                <div class="erp-fv-tv__barcode-wrap">
                    <input
                        id="orc-prod-barcode"
                        class="erp-nfe__input erp-fv-tv__input--barcode"
                        type="text"
                        wire:model.live.debounce.200ms="itemProdutoSearch"
                        wire:keydown.enter.prevent="confirmarCodigoProdutoBar($event.target.value)"
                        wire:keydown.escape.prevent="closeProdutoLookup"
                        wire:keydown.arrow-up.prevent="moveProdutoSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveProdutoSelection(1)"
                        @disabled($readOnly)
                        autocomplete="off"
                        placeholder="Código, barras ou nome do produto — Enter"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $this->produtoLookupOpen && $this->produtoResults !== [] ? 'true' : 'false' }}"
                        aria-controls="orc-prod-sugestoes"
                    >
                    @if ($this->produtoLookupOpen && $this->produtoResults !== [])
                        <ul id="orc-prod-sugestoes" class="erp-fv-tv__suggest erp-fv-tv__suggest--produto" role="listbox" aria-label="Produtos encontrados">
                            @foreach ($this->produtoResults as $index => $sug)
                                <li wire:key="orc-prod-sug-{{ $sug['id'] }}" role="presentation">
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected="{{ $this->selectedProdutoIndex === $index ? 'true' : 'false' }}"
                                        wire:click="selectProdutoResult({{ $index }})"
                                        @class(['is-selected' => $this->selectedProdutoIndex === $index])
                                    >
                                        <span class="erp-fv-tv__suggest-code">{{ $sug['codigo'] }}</span>
                                        <span class="erp-fv-tv__suggest-nome">{{ $sug['descricao'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--qtd">
                <span>Qtde</span>
                <input
                    id="orc-prod-qtd"
                    class="erp-nfe__input"
                    type="text"
                    wire:model.live="itemQuantidadeInput"
                    wire:keydown.enter.prevent="focoPrecoAposQtd"
                    @disabled($readOnly)
                    inputmode="decimal"
                    autocomplete="off"
                >
            </label>

            <div class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--preco">
                <span>Vlr. unit.</span>
                <div class="erp-fv-tv__preco-wrap">
                    <input
                        id="orc-prod-preco"
                        class="erp-nfe__input"
                        type="text"
                        wire:model.live="itemPrecoInput"
                        wire:keydown.enter.prevent="confirmPendingItemEntry"
                        @disabled($readOnly)
                        inputmode="decimal"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="erp-fv-tv__btn-desc"
                        wire:click="abrirModalDescontoItem"
                        @disabled($readOnly)
                        title="Desconto / Acréscimo (Ctrl+D)"
                    >%</button>
                </div>
            </div>

            <label class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--total-item">
                <span>Total item</span>
                <input
                    class="erp-nfe__input erp-fv-tv__input--total"
                    type="text"
                    value="{{ $this->itemTotalEntryDisplay !== '' ? $this->itemTotalEntryDisplay : '0,00' }}"
                    readonly
                    tabindex="-1"
                >
            </label>
        </div>
    </div>
</section>
