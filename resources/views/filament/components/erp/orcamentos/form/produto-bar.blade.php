@php
    $readOnly = $readOnly ?? $this->orcamentoReadOnly();
    $comSeparador = $this->orcamentoPermitirSeparadoresGrupo();
@endphp

<section @class([
    'erp-fv-tv__panel',
    'erp-fv-tv__panel--produto',
    'erp-orc-produto-bar',
    'erp-orc-produto-bar--com-separador' => $comSeparador,
])>
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
                        @disabled($readOnly)
                        autocomplete="off"
                        placeholder="Código, barras ou nome do produto — Enter"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $this->produtoLookupOpen && $this->produtoResults !== [] ? 'true' : 'false' }}"
                        aria-controls="orc-prod-sugestoes"
                    >
                    @if ($this->produtoLookupOpen && $this->produtoResults !== [])
                        <ul
                            id="orc-prod-sugestoes"
                            class="erp-fv-tv__suggest erp-fv-tv__suggest--produto"
                            role="listbox"
                            aria-label="Produtos encontrados"
                            data-orc-highlight="{{ $this->selectedProdutoIndex ?? 0 }}"
                        >
                            @foreach ($this->produtoResults as $index => $sug)
                                <li wire:key="orc-prod-sug-{{ $sug['id'] }}" role="presentation">
                                    <button
                                        type="button"
                                        role="option"
                                        data-orc-index="{{ $index }}"
                                        aria-selected="{{ $this->selectedProdutoIndex === $index ? 'true' : 'false' }}"
                                        wire:click="selectProdutoResult({{ $index }})"
                                        @class(['is-selected' => $this->selectedProdutoIndex === $index])
                                    >
                                        <span class="erp-fv-tv__suggest-code">{{ $sug['codigo'] }}</span>
                                        <span class="erp-fv-tv__suggest-nome">{{ $sug['descricao'] }}</span>
                                        <span class="erp-fv-tv__suggest-estoques">
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--atual">Atual {{ $sug['atual'] ?? '0,000' }}</span>
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--reservado">Res {{ $sug['reservado'] ?? '0,000' }}</span>
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--disponivel">Disp {{ $sug['disponivel'] ?? '0,000' }}</span>
                                        </span>
                                        <span class="erp-fv-tv__suggest-preco">R$ {{ $sug['preco'] ?? '0,00' }}@if (! empty($sug['em_promocao']))<span class="erp-promo-p" title="Promoção">P</span>@endif</span>
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
                    wire:blur="normalizeItemQuantidadeInput"
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

            @if ($comSeparador)
                <label class="erp-fv-tv__field erp-orc-separador-inline" wire:key="orc-separador-inline">
                    <span>Título do grupo</span>
                    <input
                        id="orc-separador-titulo"
                        class="erp-nfe__input"
                        type="text"
                        wire:model="separadorTituloInput"
                        wire:keydown.enter.prevent="confirmarSeparador"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="SUSPENSÃO DIANTEIRA"
                        @disabled($readOnly)
                    >
                </label>
                <div class="erp-fv-tv__field erp-orc-separador-inline-ok">
                    <span class="erp-orc-separador-inline-ok__spacer" aria-hidden="true">&nbsp;</span>
                    <button
                        type="button"
                        class="erp-orc-separador-bar__ok"
                        wire:click="confirmarSeparador"
                        @disabled($readOnly)
                        title="Inserir separador"
                    >OK</button>
                </div>
            @endif
        </div>
    </div>
</section>

