@if ($this->descontoModalOpen)
    @php($preview = $this->itemAjustePreview)
    <div class="erp-pdv-modal erp-fv-tv-desconto" role="dialog" aria-modal="true" aria-label="Desconto ou acréscimo no item">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharModalDescontoItem"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small erp-pdv-desconto">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+D — Desconto / Acréscimo</h2>
            </header>
            <div class="erp-pdv-modal__body">
                @if ($preview['descricao'] !== '')
                    <p class="erp-pdv-modal__hint">{{ $preview['descricao'] }}</p>
                @endif

                <div class="erp-pdv-desconto__seg" role="group" aria-label="Tipo">
                    <button
                        type="button"
                        wire:click="setItemAjusteTipo('desconto')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--desconto-on' => $this->itemAjusteTipo === 'desconto',
                        ])
                    >Desconto</button>
                    <button
                        type="button"
                        wire:click="setItemAjusteTipo('acrescimo')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--acrescimo-on' => $this->itemAjusteTipo === 'acrescimo',
                        ])
                    >Acréscimo</button>
                </div>

                <div class="erp-pdv-desconto__seg erp-pdv-desconto__seg--modo" role="group" aria-label="Modo">
                    <button
                        type="button"
                        wire:click="setItemAjusteModo('percentual')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--on' => $this->itemAjusteModo === 'percentual',
                        ])
                    >%</button>
                    <button
                        type="button"
                        wire:click="setItemAjusteModo('valor')"
                        @class([
                            'erp-pdv-desconto__seg-btn',
                            'erp-pdv-desconto__seg-btn--on' => $this->itemAjusteModo === 'valor',
                        ])
                    >R$</button>
                </div>

                <label class="erp-pdv-modal__label" for="erp-fv-desconto-preco">
                    {{ $this->itemAjusteModo === 'percentual' ? 'Percentual' : 'Valor (R$)' }}
                </label>
                <div class="erp-pdv-desconto__valor">
                    <span class="erp-pdv-desconto__valor-prefix">{{ $this->itemAjusteModo === 'percentual' ? '%' : 'R$' }}</span>
                    <input
                        id="erp-fv-desconto-preco"
                        type="text"
                        wire:model.live.debounce.300ms="itemAjusteValor"
                        wire:keydown.enter.prevent="confirmarItemAjuste"
                        wire:keydown.escape.prevent="fecharModalDescontoItem"
                        class="erp-pdv-modal__input erp-pdv-desconto__valor-input"
                        data-mask="money-br"
                        inputmode="decimal"
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
                <button type="button" wire:click="confirmarItemAjuste" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Aplicar</button>
                <button type="button" wire:click="fecharModalDescontoItem" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
