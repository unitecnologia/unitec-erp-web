@if ($this->deleteConfirmOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-rh-holerite-delete-modal"
        wire:keydown.escape.window="cancelDeleteHolerite"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteHolerite"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-contador-form-modal__window--confirm" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Inativar holerite</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelDeleteHolerite">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <p class="erp-contador-form-modal__confirm-text">
                    Inativar <strong>{{ $this->deleteConfirmNome }}</strong>?
                </p>
                <p class="erp-rh-holerite-form-modal__hint">O registro e o PDF permanecem no histórico (sem exclusão física do arquivo).</p>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="confirmDeleteHolerite" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger">
                    <span class="erp-pcad-actions__icon">✓</span>
                    <span class="erp-pcad-actions__label">Confirmar inativação</span>
                </button>
                <button type="button" wire:click="cancelDeleteHolerite" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
