<section
    class="erp-mov-saidas-inclusao"
    @if ($this->produtoLookupOpen && $this->produtoResults !== []) data-lookup-open="1" @endif
>
    <div class="erp-mov-saidas-inclusao__box">
        <span class="erp-mov-saidas-inclusao__legend">Incluir produto</span>

        <div class="erp-mov-saidas-inclusao__row">
            <label class="erp-mov-saidas-inclusao__field erp-mov-saidas-inclusao__field--barcode">
                <span>Código / barras / nome</span>
                <div class="erp-mov-saidas-inclusao__barcode-wrap">
                    <input
                        id="mov-saidas-inclusao-produto"
                        class="erp-mov-saidas-inclusao__input erp-mov-saidas-inclusao__input--barcode"
                        type="text"
                        wire:model.live.debounce.200ms="itemProdutoSearch"
                        wire:focus="abrirProdutoLookup"
                        wire:keydown.enter.prevent="confirmarInclusaoProduto($event.target.value)"
                        wire:keydown.escape.prevent="fecharProdutoLookup"
                        wire:keydown.arrow-up.prevent="moverProdutoSelecionado(-1)"
                        wire:keydown.arrow-down.prevent="moverProdutoSelecionado(1)"
                        data-erp-uppercase
                        autocomplete="off"
                        placeholder="Código exato, barras ou nome — Enter"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $this->produtoLookupOpen && $this->produtoResults !== [] ? 'true' : 'false' }}"
                        aria-controls="mov-saidas-inclusao-sugestoes"
                    >

                    @if ($this->produtoLookupOpen && $this->produtoResults !== [])
                        <div class="erp-mov-saidas-inclusao__suggest-wrap">
                            <ul
                                id="mov-saidas-inclusao-sugestoes"
                                class="erp-mov-saidas-inclusao__suggest"
                                role="listbox"
                                aria-label="Produtos encontrados"
                            >
                                @foreach ($this->produtoResults as $index => $sug)
                                    <li wire:key="mov-prod-sug-{{ $sug['id'] }}" role="presentation">
                                        <button
                                            type="button"
                                            id="mov-produto-sug-{{ $index }}"
                                            role="option"
                                            aria-selected="{{ (int) $this->produtoSelecionadoIndex === (int) $index ? 'true' : 'false' }}"
                                            wire:click="selecionarProdutoInclusao({{ $sug['id'] }})"
                                            @class(['is-selected' => (int) $this->produtoSelecionadoIndex === (int) $index])
                                        >
                                            <span class="erp-mov-saidas-inclusao__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                            <span class="erp-mov-saidas-inclusao__suggest-nome">{{ $sug['nome'] }}</span>
                                            <span class="erp-mov-saidas-inclusao__suggest-estoques">
                                                <span class="erp-mov-saidas-inclusao__suggest-est erp-mov-saidas-inclusao__suggest-est--atual">Atual {{ $sug['atual'] }}</span>
                                                <span class="erp-mov-saidas-inclusao__suggest-est erp-mov-saidas-inclusao__suggest-est--reservado">Res {{ $sug['reservado'] }}</span>
                                                <span class="erp-mov-saidas-inclusao__suggest-est erp-mov-saidas-inclusao__suggest-est--disponivel">Disp {{ $sug['disponivel'] }}</span>
                                            </span>
                                            <span class="erp-mov-saidas-inclusao__suggest-preco">R$ {{ $sug['preco'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($this->produtoLookupOpen && filled($this->itemProdutoSearch) && $this->itemPendingProductId === null)
                        <div class="erp-mov-saidas-inclusao__suggest erp-mov-saidas-inclusao__suggest--empty">
                            @if (ctype_digit(trim((string) $this->itemProdutoSearch)))
                                Nenhum produto com o código "{{ trim((string) $this->itemProdutoSearch) }}". Digite o nome para buscar por descrição.
                            @else
                                Nenhum produto encontrado.
                            @endif
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-mov-saidas-inclusao__field erp-mov-saidas-inclusao__field--qtd">
                <span>Qtde</span>
                <input
                    id="mov-saidas-inclusao-qtd"
                    class="erp-mov-saidas-inclusao__input"
                    type="text"
                    wire:model.live.debounce.200ms="itemEntryQtd"
                    wire:keydown.enter.prevent="focoInclusaoPrecoAposQtd($event.target.value)"
                    inputmode="decimal"
                    autocomplete="off"
                >
            </label>

            <label class="erp-mov-saidas-inclusao__field erp-mov-saidas-inclusao__field--preco">
                <span>Preço compra</span>
                <input
                    id="mov-saidas-inclusao-preco"
                    class="erp-mov-saidas-inclusao__input"
                    type="text"
                    wire:model.live.debounce.200ms="itemEntryPreco"
                    wire:keydown.enter.prevent="confirmarInclusaoPreco($event.target.value)"
                    inputmode="decimal"
                    autocomplete="off"
                >
            </label>

            <label class="erp-mov-saidas-inclusao__field erp-mov-saidas-inclusao__field--total">
                <span>Total item</span>
                <input
                    class="erp-mov-saidas-inclusao__input erp-mov-saidas-inclusao__input--total"
                    type="text"
                    value="{{ $this->itemEntryTotalDisplay !== '' ? $this->itemEntryTotalDisplay : '0,00' }}"
                    readonly
                    tabindex="-1"
                >
            </label>
        </div>
    </div>
</section>
