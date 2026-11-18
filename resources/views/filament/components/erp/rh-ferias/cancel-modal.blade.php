@if ($this->feriasCancelModalOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="closeFeriasCancelModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeFeriasCancelModal"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Cancelar gozo de férias</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeFeriasCancelModal">✕</button>
            </div>
            <div class="erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">O saldo do período será restituído. Informe o motivo.</p>
                <input type="text" wire:model="feriasCancelMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Motivo *">
                @error('feriasCancelMotivo') <span class="erp-contador-form-modal__error">{{ $message }}</span> @enderror
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="confirmCancelarFeriasGozo" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__label">Confirmar cancelamento</span>
                </button>
                <button type="button" wire:click="closeFeriasCancelModal" class="erp-pcad-actions__btn">Voltar</button>
            </div>
        </div>
    </div>
@endif
