@if ($this->lancamentoFinalizarConfirmOpen)
    <div
        class="erp-compras-confirm-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarFinalizarCompraLancamento(); }
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarFinalizarCompraLancamento(); }
        "
    >
        <div class="erp-compras-confirm-modal__backdrop" wire:click="cancelarFinalizarCompraLancamento"></div>

        <div
            class="erp-compras-confirm-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-compras-finalizar-title"
        >
            <div class="erp-compras-confirm-modal__titlebar">
                <span id="erp-compras-finalizar-title">Confirmação</span>
                <button
                    type="button"
                    class="erp-compras-confirm-modal__close"
                    wire:click="cancelarFinalizarCompraLancamento"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-compras-confirm-modal__body">
                <div class="erp-compras-confirm-modal__icon" aria-hidden="true">?</div>
                <p class="erp-compras-confirm-modal__message">
                    Tem certeza que <strong>FINALIZAR COMPRA</strong>?
                </p>
            </div>

            <div class="erp-compras-confirm-modal__actions">
                <button
                    type="button"
                    class="erp-compras-confirm-modal__btn erp-compras-confirm-modal__btn--yes"
                    wire:click="confirmarFinalizarCompraLancamento"
                >
                    Sim
                </button>
                <button
                    type="button"
                    class="erp-compras-confirm-modal__btn erp-compras-confirm-modal__btn--no"
                    wire:click="cancelarFinalizarCompraLancamento"
                >
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
