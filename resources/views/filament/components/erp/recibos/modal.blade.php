@if ($this->showForm)
    <div
        class="erp-fpgto-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleRecibosEscape(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.saveRecibo(); }
        "
    >
        <div class="erp-fpgto-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-fpgto-modal__dialog erp-recibo-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>{{ $this->formId ? 'Alterar Recibo' : 'Novo Recibo' }}</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body erp-recibo-modal__body">
                <div class="erp-recibo-modal__row">
                    <label class="erp-fpgto-field erp-recibo-modal__code">
                        <span class="erp-fpgto-field__label">Código</span>
                        <input
                            type="number"
                            min="1"
                            wire:model="form.codigo"
                            class="erp-fpgto-field__input erp-fpgto-field__input--code"
                            @readonly((bool) $this->formId)
                        >
                    </label>
                    <label class="erp-fpgto-field erp-recibo-modal__emissao">
                        <span class="erp-fpgto-field__label">Emissão</span>
                        <input
                            type="date"
                            wire:model="form.emissao"
                            class="erp-fpgto-field__input"
                        >
                    </label>
                    <label class="erp-fpgto-field erp-recibo-modal__valor">
                        <span class="erp-fpgto-field__label">Valor</span>
                        <input
                            type="text"
                            inputmode="decimal"
                            wire:model.live.debounce.250ms="form.valor"
                            class="erp-fpgto-field__input erp-fpgto-field__input--money"
                            placeholder="0,00"
                            autofocus
                        >
                    </label>
                </div>
                @error('form.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.emissao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.valor') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Extenso</span>
                    <input
                        type="text"
                        wire:model="form.extenso"
                        maxlength="500"
                        class="erp-fpgto-field__input"
                        placeholder="Preenchido automaticamente pelo valor"
                    >
                </label>
                @error('form.extenso') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Recebi de</span>
                    <input
                        type="text"
                        wire:model="form.recebi_de"
                        maxlength="200"
                        class="erp-fpgto-field__input"
                        data-erp-uppercase
                    >
                </label>
                @error('form.recebi_de') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Referente a</span>
                    <textarea
                        wire:model="form.referente_a"
                        rows="3"
                        maxlength="2000"
                        class="erp-fpgto-field__input erp-recibo-modal__textarea"
                        data-erp-uppercase
                    ></textarea>
                </label>
                @error('form.referente_a') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save" wire:click="saveRecibo">
                    <span class="erp-fpgto-modal__btn-icon">✓</span> <kbd>F5</kbd> | Salvar
                </button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel" wire:click="closeForm">
                    <span class="erp-fpgto-modal__btn-icon">✕</span> <kbd>ESC</kbd> | Sair
                </button>
            </div>
        </div>
    </div>
@endif
