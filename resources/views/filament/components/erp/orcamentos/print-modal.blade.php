@if ($this->printModalOpen)
    <div
        class="erp-lookup-modal erp-orc-print-modal"
        wire:keydown.escape="closePrintModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closePrintModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-print-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-orc-print-title">Impressão | Orçamento</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closePrintModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-print-modal__body">
                <div class="erp-orc-print-modal__icon" aria-hidden="true">
                    <span class="erp-orc-print-modal__icon-printer">🖨</span>
                </div>

                <div class="erp-orc-print-modal__options">
                    <button type="button" wire:click="visualizarOrcamentoImpressao" class="erp-orc-print-modal__option">
                        Visualizar
                    </button>
                    <button type="button" wire:click="imprimirBobinaOrcamento" class="erp-orc-print-modal__option">
                        Bobina
                    </button>
                    <button type="button" wire:click="closePrintModal" class="erp-orc-print-modal__option erp-orc-print-modal__option--exit">
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
