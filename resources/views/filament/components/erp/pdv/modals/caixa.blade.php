@if (in_array($this->activeModal, ['abrir_caixa', 'fechar_caixa'], true))
    <div class="erp-pdv-modal" role="dialog" aria-labelledby="erp-pdv-caixa-title">
        <div class="erp-pdv-modal__backdrop" wire:click="closePdvModal"></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--form">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-pdv-caixa-title">
                    {{ $this->activeModal === 'abrir_caixa' ? 'Abrir Caixa' : 'Fechar Caixa' }}
                </h2>
            </header>

            <div class="erp-pdv-modal__body">
                @if ($this->activeModal === 'abrir_caixa')
                    <p class="erp-pdv-modal__hint">Informe o valor inicial do caixa (troco / fundo de caixa):</p>

                    <label class="erp-pdv-modal__label" for="erp-pdv-abertura-valor">Valor de Abertura</label>
                    <input
                        id="erp-pdv-abertura-valor"
                        type="text"
                        wire:model="aberturaForm.valor"
                        data-mask="money"
                        class="erp-pdv-modal__input erp-pdv-form__input--money"
                        autocomplete="off"
                    >
                @else
                    <p class="erp-pdv-modal__confirm-text">
                        Deseja fechar o caixa? Nenhuma venda poderá ser realizada até reabrir.
                    </p>
                @endif
            </div>

            <footer class="erp-pdv-modal__footer">
                @if ($this->activeModal === 'abrir_caixa')
                    <button type="button" wire:click="confirmAbrirCaixa" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">
                        <kbd>F2</kbd> Abrir
                    </button>
                @else
                    <button type="button" wire:click="confirmFecharCaixa" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">
                        <kbd>F2</kbd> Fechar
                    </button>
                @endif
                <button type="button" wire:click="closePdvModal" class="erp-pdv-modal__btn">
                    <kbd>Esc</kbd> Cancelar
                </button>
            </footer>
        </div>
    </div>
@endif
