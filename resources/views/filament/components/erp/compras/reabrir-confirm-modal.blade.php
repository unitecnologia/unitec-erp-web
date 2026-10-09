@if ($this->reabrirConfirmCompraId)
    <div
        class="erp-compras-confirm-modal"
        x-data="{
            enviando: false,
            confirmar() {
                if (this.enviando) return;
                this.enviando = true;
                this.$wire.confirmarReabrirCompra().finally(() => { this.enviando = false; });
            },
        }"
        x-init="$nextTick(() => $refs.sim?.focus())"
        x-on:erp-compras-reabrir-fechado.window="$el.remove()"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); if (! enviando) $wire.cancelarReabrirCompra(); }
            if ($event.key === 'Enter') { $event.preventDefault(); confirmar(); }
        "
    >
        <div class="erp-compras-confirm-modal__backdrop" wire:click="cancelarReabrirCompra"></div>

        <div
            class="erp-compras-confirm-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-compras-reabrir-title"
        >
            <div class="erp-compras-confirm-modal__titlebar">
                <span id="erp-compras-reabrir-title">Confirmação</span>
                <button
                    type="button"
                    class="erp-compras-confirm-modal__close"
                    wire:click="cancelarReabrirCompra"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-compras-confirm-modal__body">
                <div class="erp-compras-confirm-modal__icon" aria-hidden="true">?</div>
                <p class="erp-compras-confirm-modal__message">
                    Tem certeza que deseja <strong>REABRIR A COMPRA</strong>?<br>
                    Estoque, preços e financeiro gerados na finalização serão estornados.
                </p>
            </div>

            <div class="erp-compras-confirm-modal__actions">
                <button
                    type="button"
                    x-ref="sim"
                    class="erp-compras-confirm-modal__btn erp-compras-confirm-modal__btn--yes"
                    x-on:click="confirmar()"
                    x-bind:disabled="enviando"
                    wire:loading.attr="disabled"
                    wire:target="confirmarReabrirCompra"
                >
                    Sim
                </button>
                <button
                    type="button"
                    class="erp-compras-confirm-modal__btn erp-compras-confirm-modal__btn--no"
                    wire:click="cancelarReabrirCompra"
                >
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
