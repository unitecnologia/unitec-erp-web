@if ($this->deleteConfirmOpen)
    <div
        class="erp-lookup-modal erp-vendedor-delete-modal"
        wire:keydown.escape.window="cancelDeleteVendedor"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteVendedor"></div>

        <div
            class="erp-lookup-modal__window erp-vendedor-delete-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-vendedor-delete-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-vendedor-delete-title">Confirmação</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="cancelDeleteVendedor"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-vendedor-delete-modal__body">
                <p class="erp-vendedor-delete-modal__message">Deseja excluir?</p>
                @if (filled($this->deleteConfirmNome))
                    <p class="erp-vendedor-delete-modal__detail">{{ $this->deleteConfirmNome }}</p>
                @endif
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-vendedor-delete-modal__actions">
                <button type="button" wire:click="confirmDeleteVendedor" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim</span>
                </button>
                <button type="button" wire:click="cancelDeleteVendedor" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
@endif
