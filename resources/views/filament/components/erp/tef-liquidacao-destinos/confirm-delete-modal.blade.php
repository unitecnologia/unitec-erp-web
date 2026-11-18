@if ($this->deleteConfirmOpen)
    <div
        class="erp-lookup-modal erp-confirm-delete-modal"
        wire:keydown.escape.window="cancelDeleteDestino"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteDestino"></div>
        <div class="erp-lookup-modal__window erp-confirm-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Excluir destino</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelDeleteDestino" title="Fechar">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <p>Excluir a configuração <strong>{{ $this->deleteConfirmNome }}</strong>?</p>
                <p class="erp-tef-liq-dest-form-modal__hint">Não altera transações SuperTEF nem gera movimento financeiro.</p>
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="cancelDeleteDestino">
                    Cancelar
                </button>
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--danger" wire:click="confirmDeleteDestino">
                    Excluir
                </button>
            </div>
        </div>
    </div>
@endif
