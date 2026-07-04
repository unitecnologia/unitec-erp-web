@if ($this->showAjusteForm)
    <div
        class="erp-fpgto-modal erp-ajuste-modal"
        x-data
        x-on:keydown.escape.window="$wire.closeAjusteForm()"
        x-on:keydown.window="if ($event.key === 'F5') { $event.preventDefault(); $wire.saveAjusteForm(); }"
    >
        <div class="erp-fpgto-modal__backdrop" wire:click="closeAjusteForm"></div>

        <div class="erp-fpgto-modal__dialog erp-ajuste-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>Lista de Ajustes de Estoque — {{ $this->ajusteFormId ? 'Alterar ajuste' : 'Novo ajuste' }}</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeAjusteForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body erp-ajuste-modal__body">
                <div class="erp-ajuste-modal__grid">
                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Cód. Ajust.</span>
                        <input type="text" readonly tabindex="-1" value="{{ $this->ajusteForm['codigo_display'] ?? '' }}" class="erp-fpgto-field__input erp-fpgto-field__input--code erp-ajuste-modal__readonly">
                    </label>

                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Data</span>
                        <input type="date" wire:model="ajusteForm.data" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                    </label>

                    <div class="erp-ajuste-modal__row3">
                        <label class="erp-fpgto-field erp-ajuste-modal__field--compact">
                            <span class="erp-fpgto-field__label">Cód. Int.</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.codigo_interno"
                                wire:keydown.enter="resolveProdutoCodigoInterno"
                                wire:blur="resolveProdutoCodigoInterno"
                                @disabled($this->ajusteFormId)
                                class="erp-fpgto-field__input"
                                placeholder="Código"
                            >
                        </label>

                        <label class="erp-fpgto-field erp-ajuste-modal__field--compact">
                            <span class="erp-fpgto-field__label">Cód. Barras</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.codigo_barras"
                                wire:keydown.enter="resolveProdutoCodigoBarras"
                                wire:blur="resolveProdutoCodigoBarras"
                                @disabled($this->ajusteFormId)
                                class="erp-fpgto-field__input"
                                placeholder="EAN / barras"
                            >
                        </label>

                        <label class="erp-fpgto-field erp-ajuste-modal__field--compact">
                            <span class="erp-fpgto-field__label">Referência</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.referencia"
                                wire:keydown.enter="resolveProdutoReferencia"
                                wire:blur="resolveProdutoReferencia"
                                @disabled($this->ajusteFormId)
                                class="erp-fpgto-field__input"
                                placeholder="Referência"
                            >
                        </label>
                    </div>

                    <label class="erp-fpgto-field erp-ajuste-modal__field--full">
                        <span class="erp-fpgto-field__label">Descrição</span>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="ajusteForm.descricao_busca"
                            @disabled($this->ajusteFormId)
                            class="erp-fpgto-field__input"
                            placeholder="Digite nome, código, barras ou referência (mín. 2 letras)"
                            autocomplete="off"
                        >
                    </label>

                    @if (! $this->ajusteFormId && count($this->produtoSugestoes) > 0)
                        <div class="erp-ajuste-modal__sugestoes" role="listbox">
                            @foreach ($this->produtoSugestoes as $sugestao)
                                <button
                                    type="button"
                                    wire:click="selecionarProdutoSugestao({{ $sugestao['id'] }})"
                                    class="erp-ajuste-modal__sugestao"
                                    role="option"
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

                    <div class="erp-ajuste-modal__row2">
                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Estoque Atual</span>
                            <input type="text" readonly tabindex="-1" value="{{ $this->ajusteForm['estoque_atual'] ?? '' }}" class="erp-fpgto-field__input erp-fpgto-field__input--num erp-ajuste-modal__readonly">
                        </label>

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Qtd. Ajuste</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.quantidade"
                                inputmode="decimal"
                                class="erp-fpgto-field__input erp-fpgto-field__input--num"
                                placeholder="0"
                            >
                        </label>
                    </div>

                    <p class="erp-ajuste-modal__hint">
                        Use quantidade positiva para entrar estoque e negativa para sair. Pressione <kbd>Enter</kbd> nos campos de código para localizar o produto.
                    </p>
                </div>
            </div>

            <div class="erp-fpgto-modal__footer erp-ajuste-modal__footer">
                <button type="button" wire:click="saveAjusteForm" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save">
                    <span class="erp-fpgto-modal__btn-icon">✓</span>
                    <span><kbd>F5</kbd> | Gravar</span>
                </button>
                <button type="button" wire:click="closeAjusteForm" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel">
                    <span class="erp-fpgto-modal__btn-icon">✕</span>
                    <span>Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
