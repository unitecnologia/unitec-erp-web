@if ($this->deleteConfirmOpen)
    <div class="erp-lookup-modal erp-contador-delete-modal" wire:keydown.escape.window="cancelDeleteExame">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteExame"></div>
        <div class="erp-lookup-modal__window erp-contador-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Inativar exame</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelDeleteExame">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-delete-modal__body">
                <p class="erp-contador-delete-modal__message">Inativar preserva o histórico e o ASO anexado.</p>
                @if (filled($this->deleteConfirmNome))
                    <p class="erp-contador-delete-modal__detail">{{ $this->deleteConfirmNome }}</p>
                @endif
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-delete-modal__actions">
                <button type="button" wire:click="confirmDeleteExame" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim</span>
                </button>
                <button type="button" wire:click="cancelDeleteExame" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif
