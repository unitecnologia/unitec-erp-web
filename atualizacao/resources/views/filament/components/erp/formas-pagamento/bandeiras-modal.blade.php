@if ($this->showBandeiraForm)
    <div class="erp-fpgto-modal erp-bandeiras-form-modal" x-data
         x-on:keydown.escape.window="$wire.closeBandeiraForm()"
         x-on:keydown.window="if ($event.key === 'F2') { $event.preventDefault(); $wire.saveBandeira(); }">
        <div class="erp-fpgto-modal__backdrop" wire:click="closeBandeiraForm"></div>

        <div class="erp-fpgto-modal__dialog erp-bandeiras-form-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>{{ $this->bandeiraFormId ? 'Alterar bandeira' : 'Nova bandeira' }}</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeBandeiraForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body">
                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Código</span>
                    <input type="number" min="1" wire:model="bandeiraForm.codigo" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                </label>
                @error('bandeiraForm.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Nome</span>
                    <input type="text" maxlength="60" wire:model="bandeiraForm.nome" class="erp-fpgto-field__input" autofocus>
                </label>
                @error('bandeiraForm.nome') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-check">
                    <input type="checkbox" wire:model="bandeiraForm.ativo"> Ativo
                </label>
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn" wire:click="closeBandeiraForm">Cancelar</button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--primary" wire:click="saveBandeira">
                    <kbd>F2</kbd> Gravar
                </button>
            </div>
        </div>
    </div>
@endif

@if ($this->showMaquininhaForm)
    <div class="erp-fpgto-modal erp-bandeiras-form-modal" x-data
         x-on:keydown.escape.window="$wire.closeMaquininhaForm()"
         x-on:keydown.window="if ($event.key === 'F2') { $event.preventDefault(); $wire.saveMaquininha(); }">
        <div class="erp-fpgto-modal__backdrop" wire:click="closeMaquininhaForm"></div>

        <div class="erp-fpgto-modal__dialog erp-bandeiras-form-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>{{ $this->maquininhaFormId ? 'Alterar maquininha' : 'Nova maquininha' }}</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeMaquininhaForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body">
                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Código</span>
                    <input type="number" min="1" wire:model="maquininhaForm.codigo" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                </label>
                @error('maquininhaForm.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-field">
                    <span class="erp-fpgto-field__label">Nome</span>
                    <input type="text" maxlength="60" wire:model="maquininhaForm.nome" class="erp-fpgto-field__input" autofocus>
                </label>
                @error('maquininhaForm.nome') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <label class="erp-fpgto-check">
                    <input type="checkbox" wire:model="maquininhaForm.ativo"> Ativo
                </label>
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn" wire:click="closeMaquininhaForm">Cancelar</button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--primary" wire:click="saveMaquininha">
                    <kbd>F2</kbd> Gravar
                </button>
            </div>
        </div>
    </div>
@endif
