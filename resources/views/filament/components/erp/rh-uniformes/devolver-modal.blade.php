@if ($this->devolverModalOpen)
    <div class="erp-lookup-modal erp-contador-form-modal" wire:keydown.escape.window="closeDevolverModal" wire:keydown.f5.window.prevent="confirmDevolver">
        <div class="erp-lookup-modal__backdrop" wire:click="closeDevolverModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Devolver uniforme</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeDevolverModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-pcad-form" data-erp-form>
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-unif-dev-data">Data *</label>
                        <input id="rh-unif-dev-data" type="date" wire:model="devolverData" class="erp-pcad-form__input erp-pcad-form__input--date" autofocus>
                    </div>
                    @error('devolverData') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
                    <div class="erp-pcad-form__row">
                        <label class="erp-pcad-form__label" for="rh-unif-dev-obs">Observação</label>
                        <textarea id="rh-unif-dev-obs" rows="2" maxlength="500" wire:model="devolverObs" class="erp-pcad-form__textarea erp-pcad-form__input--grow"></textarea>
                    </div>
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="confirmDevolver" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Confirmar</span>
                </button>
                <button type="button" wire:click="closeDevolverModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
@endif
