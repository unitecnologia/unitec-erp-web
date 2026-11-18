@if ($this->feriasSaldoModalOpen)
    <div class="erp-lookup-modal erp-contador-form-modal" wire:keydown.escape.window="closeFeriasSaldoModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeFeriasSaldoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Ajuste administrativo de saldo</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeFeriasSaldoModal">✕</button>
            </div>
            <div class="erp-contador-form-modal__body">
                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                    <label class="erp-pcad-form__label" for="rh-fs-saldo">Novo saldo (dias)</label>
                    <input id="rh-fs-saldo" type="number" min="0" max="365" wire:model="feriasSaldoForm.novo_saldo" class="erp-pcad-form__input">
                </div>
                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                    <label class="erp-pcad-form__label" for="rh-fs-mot">Motivo *</label>
                    <input id="rh-fs-mot" type="text" maxlength="255" wire:model="feriasSaldoForm.motivo" class="erp-pcad-form__input erp-pcad-form__input--grow">
                    @error('feriasSaldoForm.motivo') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="saveAjusteSaldoFerias" class="erp-pcad-actions__btn">Gravar ajuste</button>
                <button type="button" wire:click="closeFeriasSaldoModal" class="erp-pcad-actions__btn">Cancelar</button>
            </div>
        </div>
    </div>
@endif
