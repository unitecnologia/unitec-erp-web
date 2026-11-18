@if ($this->deleteConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="cancelDeleteOcorrencia">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteOcorrencia"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Cancelar ocorrência</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelDeleteOcorrencia">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">O cancelamento preserva o histórico e os anexos para auditoria.</p>
                @if (filled($this->deleteConfirmNome))
                    <p class="erp-contador-delete-modal__detail">{{ $this->deleteConfirmNome }}</p>
                @endif
                <label class="erp-pcad-form__label" for="rh-oc-cancel-motivo">Motivo (opcional)</label>
                <input id="rh-oc-cancel-motivo" type="text" wire:model="cancelMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255">
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmCancelarOcorrencia" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Confirmar</span>
                </button>
                <button type="button" wire:click="cancelDeleteOcorrencia" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif
