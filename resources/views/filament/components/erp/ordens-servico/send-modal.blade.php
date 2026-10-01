@if ($this->sendModalOpen)
    <div
        class="erp-lookup-modal erp-orc-print-modal"
        wire:keydown.escape.window="closeSendModal"
        x-data
        x-init="$nextTick(() => $el.querySelector('.erp-orc-print-modal__option')?.focus())"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeSendModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-os-send-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-send-title">Enviar | Ordem de Serviço</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeSendModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-print-modal__body">
                <div class="erp-orc-print-modal__icon" aria-hidden="true">
                    <span class="erp-orc-print-modal__icon-printer">✉</span>
                </div>

                <div class="erp-orc-print-modal__options">
                    <button type="button" wire:click="openEmailModalCompleta" class="erp-orc-print-modal__option">
                        Envio normal
                    </button>
                    <button type="button" wire:click="openEmailModalTecnica" class="erp-orc-print-modal__option">
                        OS Técnica ao mecânico
                    </button>
                    <button type="button" wire:click="closeSendModal" class="erp-orc-print-modal__option erp-orc-print-modal__option--exit">
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
