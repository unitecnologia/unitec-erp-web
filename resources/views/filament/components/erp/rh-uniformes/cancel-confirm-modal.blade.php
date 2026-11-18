@if ($this->cancelConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="closeCancelConfirm">
        <div class="erp-lookup-modal__backdrop" wire:click="closeCancelConfirm"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Cancelar entrega</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeCancelConfirm">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">Cancelar mantém o histórico (não apaga).</p>
                @if (filled($this->cancelNome))
                    <p class="erp-contador-delete-modal__detail">{{ $this->cancelNome }}</p>
                @endif
                <div class="erp-pcad-form__row" style="margin-top:0.5rem">
                    <label class="erp-pcad-form__label" for="rh-unif-cancel-motivo">Motivo</label>
                    <input id="rh-unif-cancel-motivo" type="text" wire:model="cancelMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmCancelarEntrega" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim</span>
                </button>
                <button type="button" wire:click="closeCancelConfirm" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif
