@if ($this->cancelConfirmOpen)
    <div class="erp-lookup-modal erp-confirm-delete-modal" wire:keydown.escape.window="cancelCancelarLote">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelCancelarLote"></div>
        <div class="erp-lookup-modal__window erp-confirm-delete-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Cancelar lote</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelCancelarLote">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <p>Cancelar <strong>{{ $this->cancelConfirmNome }}</strong>?</p>
                <p class="erp-tef-liq-lote-form-modal__hint">Libera as intenções para outro lote. Não altera SuperTEF nem gera movimento financeiro.</p>
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="cancelCancelarLote">Voltar</button>
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--danger" wire:click="confirmCancelarLote">Cancelar lote</button>
            </div>
        </div>
    </div>
@endif
