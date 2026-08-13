@if ($this->showAjusteForm)
    <div
        class="erp-fpgto-modal erp-ajuste-modal"
        x-data
        x-on:keydown.escape.window="$wire.handleAjusteEscape()"
        x-on:keydown.window="if ($event.key === 'F5') { $event.preventDefault(); $wire.saveAjusteForm(); }"
        x-on:erp-ajuste-scroll-produto-sugestao.window="
            $nextTick(() => {
                const i = $event.detail.index ?? 0;
                document.getElementById('erp-ajuste-produto-sug-' + i)?.scrollIntoView({ block: 'nearest' });
            })
        "
    >
        <div class="erp-fpgto-modal__backdrop erp-ajuste-modal__backdrop" wire:click="closeAjusteForm"></div>

        <div class="erp-fpgto-modal__dialog erp-ajuste-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-ajuste-modal-title">
            <header class="erp-ajuste-modal__header">
                <div>
                    <p class="erp-ajuste-modal__eyebrow">Estoque</p>
                    <h2 id="erp-ajuste-modal-title" class="erp-ajuste-modal__title">
                        {{ $this->ajusteFormId ? 'Alterar ajuste' : 'Novo ajuste' }}
                    </h2>
                </div>
                <button type="button" class="erp-ajuste-modal__close" wire:click="closeAjusteForm" aria-label="Fechar">✕</button>
            </header>

            <div class="erp-fpgto-modal__body erp-ajuste-modal__body">
                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Identificação</span>
                    </div>
                    <div class="erp-ajuste-modal__meta">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. ajuste</span>
                            <input type="text" readonly tabindex="-1" value="{{ $this->ajusteForm['codigo_display'] ?? '' }}" class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--sm">
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Data</span>
                            <input type="date" wire:model="ajusteForm.data" class="erp-ajuste-modal__input erp-ajuste-modal__input--date">
                        </label>
                    </div>
                </section>

                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Produto</span>
                        <small>Enter nos códigos para localizar</small>
                    </div>

                    <div class="erp-ajuste-modal__row3">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. int.</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.codigo_interno"
                                wire:keydown.enter="resolveProdutoCodigoInterno"
                                wire:blur="resolveProdutoCodigoInterno"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="Código"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. barras</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.codigo_barras"
                                wire:keydown.enter="resolveProdutoCodigoBarras"
                                wire:blur="resolveProdutoCodigoBarras"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="EAN / barras"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Referência</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.referencia"
                                wire:keydown.enter="resolveProdutoReferencia"
                                wire:blur="resolveProdutoReferencia"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="Referência"
                            >
                        </label>
                    </div>

                    <label class="erp-ajuste-modal__field erp-ajuste-modal__field--full">
                        <span class="erp-ajuste-modal__label">Descrição</span>
                        <input
                            id="erp-ajuste-descricao-busca"
                            type="text"
                            wire:model.live.debounce.300ms="ajusteForm.descricao_busca"
                            wire:keydown.enter.prevent="confirmarProdutoSugestao"
                            wire:keydown.escape.prevent="fecharSugestoesProduto"
                            x-on:keydown.arrow-down.prevent="$wire.moverSugestaoProduto(1)"
                            x-on:keydown.arrow-up.prevent="$wire.moverSugestaoProduto(-1)"
                            @disabled($this->ajusteFormId)
                            class="erp-ajuste-modal__input"
                            placeholder="Digite nome, código, barras ou referência (mín. 2 letras)"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="{{ count($this->produtoSugestoes) > 0 ? 'true' : 'false' }}"
                            aria-controls="erp-ajuste-produto-sugestoes"
                        >
                    </label>

                    @if (! $this->ajusteFormId && count($this->produtoSugestoes) > 0)
                        <div id="erp-ajuste-produto-sugestoes" class="erp-ajuste-modal__sugestoes" role="listbox" aria-label="Produtos encontrados">
                            @foreach ($this->produtoSugestoes as $index => $sugestao)
                                <button
                                    type="button"
                                    id="erp-ajuste-produto-sug-{{ $index }}"
                                    wire:key="erp-ajuste-prod-sug-{{ $sugestao['id'] }}"
                                    wire:click="selecionarProdutoSugestao({{ $sugestao['id'] }})"
                                    @class([
                                        'erp-ajuste-modal__sugestao',
                                        'is-selected' => $this->selectedProdutoSugestaoIndex === $index,
                                    ])
                                    role="option"
                                    aria-selected="{{ $this->selectedProdutoSugestaoIndex === $index ? 'true' : 'false' }}"
                                    tabindex="-1"
                                >
                                    <span class="erp-ajuste-modal__sugestao-cod">{{ $sugestao['codigo'] }}</span>
                                    <span class="erp-ajuste-modal__sugestao-desc">{{ $sugestao['descricao'] }}</span>
                                    <span class="erp-ajuste-modal__sugestao-est">Est. {{ $sugestao['estoque'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if (filled($this->ajusteForm['product_id'] ?? null))
                        <p class="erp-ajuste-modal__produto-ok">
                            Produto selecionado: <strong>{{ $this->ajusteForm['descricao_busca'] ?? '' }}</strong>
                        </p>
                    @endif
                </section>

                <section class="erp-ajuste-modal__section erp-ajuste-modal__section--qty">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Quantidade</span>
                        <small>+ entrada · − saída</small>
                    </div>
                    <div class="erp-ajuste-modal__row2">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Estoque atual</span>
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                value="{{ $this->ajusteForm['estoque_atual'] ?? '' }}"
                                class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--num"
                                aria-readonly="true"
                                onfocus="this.blur()"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Qtd. ajuste</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.quantidade"
                                inputmode="decimal"
                                class="erp-ajuste-modal__input erp-ajuste-modal__input--num erp-ajuste-modal__input--emphasis"
                                placeholder="0"
                            >
                        </label>
                    </div>
                    <p class="erp-ajuste-modal__hint">
                        Quantidade positiva entra estoque; negativa sai. Pressione <kbd>Enter</kbd> nos campos de código para localizar.
                    </p>
                </section>
            </div>

            <footer class="erp-ajuste-modal__footer">
                <button type="button" wire:click="closeAjusteForm" class="erp-ajuste-modal__btn erp-ajuste-modal__btn--ghost">
                    Cancelar
                </button>
                <button type="button" wire:click="saveAjusteForm" class="erp-ajuste-modal__btn erp-ajuste-modal__btn--primary">
                    <kbd>F5</kbd>
                    <span>Gravar</span>
                </button>
            </footer>
        </div>
    </div>
@endif
