<section class="erp-nfe-inclusao" @if ($this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== []) data-lookup-open="1" @endif>
    <div class="erp-nfe-inclusao__box">
        <span class="erp-nfe-inclusao__legend">Produto</span>

        <div class="erp-nfe-inclusao__row">
            <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--barcode">
                <span>Código / barras / nome</span>
                <div class="erp-nfe-inclusao__barcode-wrap">
                    <input
                        id="nfe-inclusao-produto"
                        class="erp-nfe-inclusao__input erp-nfe-inclusao__input--barcode"
                        type="text"
                        wire:model.live.debounce.200ms="nfeItemProdutoSearch"
                        wire:focus="openNfeProdutoLookup"
                        wire:keydown.enter.prevent="confirmarNfeInclusaoProduto($event.target.value)"
                        wire:keydown.escape.prevent="closeNfeProdutoLookup"
                        wire:keydown.arrow-up.prevent="moveNfeProdutoSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveNfeProdutoSelection(1)"
                        data-erp-uppercase
                        autocomplete="off"
                        placeholder="Código exato, barras ou nome — Enter"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== [] ? 'true' : 'false' }}"
                        aria-controls="nfe-inclusao-sugestoes"
                    >

                    @if ($this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== [])
                        <div class="erp-nfe-inclusao__suggest-wrap">
                            <ul
                                id="nfe-inclusao-sugestoes"
                                class="erp-nfe-inclusao__suggest"
                                role="listbox"
                                aria-label="Produtos encontrados"
                            >
                                @foreach ($this->nfeProdutoResults as $index => $sug)
                                    <li wire:key="nfe-prod-sug-{{ $sug['id'] }}" role="presentation">
                                        <button
                                            type="button"
                                            id="nfe-produto-sug-{{ $index }}"
                                            role="option"
                                            aria-selected="{{ (int) $this->nfeSelectedProdutoIndex === (int) $index ? 'true' : 'false' }}"
                                            wire:click="selecionarNfeProdutoInclusao({{ $sug['id'] }})"
                                            @class(['is-selected' => (int) $this->nfeSelectedProdutoIndex === (int) $index])
                                        >
                                            <span class="erp-nfe-inclusao__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                            <span class="erp-nfe-inclusao__suggest-nome">{{ $sug['nome'] ?? $sug['descricao'] ?? '—' }}</span>
                                            <span class="erp-nfe-inclusao__suggest-estoques">
                                                <span class="erp-nfe-inclusao__suggest-est erp-nfe-inclusao__suggest-est--atual">Atual {{ $sug['atual'] ?? '0,000' }}</span>
                                                <span class="erp-nfe-inclusao__suggest-est erp-nfe-inclusao__suggest-est--reservado">Res {{ $sug['reservado'] ?? '0,000' }}</span>
                                                <span class="erp-nfe-inclusao__suggest-est erp-nfe-inclusao__suggest-est--disponivel">Disp {{ $sug['disponivel'] ?? '0,000' }}</span>
                                            </span>
                                            <span class="erp-nfe-inclusao__suggest-preco">R$ {{ $sug['preco'] ?? '0,00' }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($this->nfeProdutoLookupOpen && filled($this->nfeItemProdutoSearch) && $this->nfeItemPendingProductId === null)
                        <div class="erp-nfe-inclusao__suggest erp-nfe-inclusao__suggest--empty">
                            @if (ctype_digit(trim((string) $this->nfeItemProdutoSearch)))
                                Nenhum produto com o código "{{ trim((string) $this->nfeItemProdutoSearch) }}". Digite o nome para buscar por descrição.
                            @else
                                Nenhum produto encontrado.
                            @endif
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--qtd">
                <span>Qtde</span>
                <input
                    id="nfe-inclusao-qtd"
                    class="erp-nfe-inclusao__input"
                    type="text"
                    wire:model.live.debounce.200ms="nfeItemEntryQtd"
                    wire:keydown.enter.prevent="focoNfeInclusaoPrecoAposQtd($event.target.value)"
                    inputmode="decimal"
                    autocomplete="off"
                >
            </label>

            <div class="erp-nfe-inclusao__field erp-nfe-inclusao__field--preco">
                <span>Vlr. unit.</span>
                <div class="erp-nfe-inclusao__preco-wrap">
                    <input
                        id="nfe-inclusao-preco"
                        class="erp-nfe-inclusao__input"
                        type="text"
                        wire:model.live.debounce.200ms="nfeItemEntryPreco"
                        wire:keydown.enter.prevent="confirmarNfeInclusaoPreco($event.target.value)"
                        inputmode="decimal"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="erp-nfe-inclusao__btn-pct"
                        wire:click="abrirNfeModalDescontoItem"
                        title="Desconto / Acréscimo (Ctrl+D)"
                    >
                        %
                    </button>
                </div>
            </div>

            <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--total">
                <span>Total item</span>
                <input
                    class="erp-nfe-inclusao__input erp-nfe-inclusao__input--total"
                    type="text"
                    value="{{ $this->nfeItemEntryTotalDisplay !== '' ? $this->nfeItemEntryTotalDisplay : '0,00' }}"
                    readonly
                    tabindex="-1"
                >
            </label>
        </div>
    </div>
</section>
