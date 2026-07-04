@if ($this->activeModal === 'desconto_item')
    @php($preview = $this->descontoItemPreview)
    <div class="erp-pdv-modal" role="dialog" aria-label="Desconto ou acréscimo no item">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small erp-pdv-desconto">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+D — Desconto / Acréscimo</h2>
            </header>
            <div class="erp-pdv-modal__body">
                @if ($this->cupomItemSelecionado)
                    <p class="erp-pdv-modal__hint">{{ $this->cupomItemSelecionado['descricao'] ?? '' }}</p>
                @endif

                <div class="erp-pdv-desconto__seg" role="group" aria-label="Tipo">
                    <button
                        type="button"
                        wire:click="setDescontoItemTipo('desconto')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--desconto-on' => $this->descontoItemTipo === 'desconto',
                        ])
                    >Desconto</button>
                    <button
                        type="button"
                        wire:click="setDescontoItemTipo('acrescimo')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--acrescimo-on' => $this->descontoItemTipo === 'acrescimo',
                        ])
                    >Acréscimo</button>
                </div>

                <div class="erp-pdv-desconto__seg erp-pdv-desconto__seg--modo" role="group" aria-label="Modo">
                    <button
                        type="button"
                        wire:click="setDescontoItemModo('percentual')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--on' => $this->descontoItemModo === 'percentual',
                        ])
                    >%</button>
                    <button
                        type="button"
                        wire:click="setDescontoItemModo('valor')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--on' => $this->descontoItemModo === 'valor',
                        ])
                    >R$</button>
                </div>

                <label class="erp-pdv-modal__label" for="erp-pdv-desconto-preco">
                    {{ $this->descontoItemModo === 'percentual' ? 'Percentual' : 'Valor (R$)' }}
                </label>
                <div class="erp-pdv-desconto__valor">
                    <span class="erp-pdv-desconto__valor-prefix">{{ $this->descontoItemModo === 'percentual' ? '%' : 'R$' }}</span>
                    <input
                        id="erp-pdv-desconto-preco"
                        type="text"
                        wire:model.live.debounce.300ms="descontoItemValor"
                        class="erp-pdv-modal__input erp-pdv-desconto__valor-input"
                        data-mask="money"
                        inputmode="numeric"
                        autocomplete="off"
                    >
                </div>

                <div @class([
                    'erp-pdv-desconto__preview',
                    'erp-pdv-desconto__preview--desconto' => $preview['temAjuste'] && $preview['tipo'] === 'desconto',
                    'erp-pdv-desconto__preview--acrescimo' => $preview['temAjuste'] && $preview['tipo'] === 'acrescimo',
                ])>
                    <div class="erp-pdv-desconto__preview-row">
                        <span>Preço</span>
                        <span class="erp-pdv-desconto__preview-precos">
                            <span class="erp-pdv-desconto__preview-de">R$ {{ $preview['base'] }}</span>
                            <span class="erp-pdv-desconto__preview-seta">→</span>
                            <strong class="erp-pdv-desconto__preview-novo">R$ {{ $preview['novoPreco'] }}</strong>
                        </span>
                    </div>
                    <div class="erp-pdv-desconto__preview-row erp-pdv-desconto__preview-row--total">
                        <span>Total do item</span>
                        <strong>R$ {{ $preview['total'] }}</strong>
                    </div>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmDescontoItem" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Aplicar</button>
                <button type="button" wire:click="closePdvModal" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
