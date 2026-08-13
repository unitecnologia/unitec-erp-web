@if ($this->printModalOpen)
    <div
        class="erp-lookup-modal erp-recibo-print-modal"
        wire:keydown.escape.window="closePrintModal"
        x-data
        x-init="$nextTick(() => $el.querySelector('.erp-recibo-print-modal__option')?.focus())"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closePrintModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-recibo-print-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-recibo-print-title">Impressão | Recibo</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closePrintModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-recibo-print-modal__body">
                <div class="erp-recibo-print-modal__icon" aria-hidden="true">
                    <span class="erp-recibo-print-modal__icon-printer">🖨</span>
                </div>

                <div class="erp-recibo-print-modal__options">
                    <button type="button" wire:click="visualizarReciboImpressao" class="erp-recibo-print-modal__option">
                        Visualizar
                    </button>
                    <button type="button" wire:click="imprimirBobinaRecibo" class="erp-recibo-print-modal__option">
                        Bobina
                    </button>
                    <button type="button" wire:click="closePrintModal" class="erp-recibo-print-modal__option erp-recibo-print-modal__option--exit">
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
