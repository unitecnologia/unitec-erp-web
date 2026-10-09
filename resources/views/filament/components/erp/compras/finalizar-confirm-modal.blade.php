@if ($this->lancamentoFinalizarConfirmOpen)
    <div
        class="erp-compras-confirm-modal"
        x-data="{
            enviando: false,
            confirmar() {
                if (this.enviando || this.$wire.lancamentoFinalizando) return;
                this.enviando = true;
                this.$wire.confirmarFinalizarCompraLancamento().finally(() => { this.enviando = false; });
            },
        }"
        x-on:erp-compras-lancamento-fechado.window="$el.remove()"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); if (! enviando) $wire.cancelarFinalizarCompraLancamento(); }
            if ($event.key === 'Enter') { $event.preventDefault(); confirmar(); }
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
                    x-on:click="confirmar()"
                    x-bind:disabled="enviando || @js($this->lancamentoFinalizando)"
                    wire:loading.attr="disabled"
                    wire:target="confirmarFinalizarCompraLancamento,concluirLancamentoParcelas"
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
