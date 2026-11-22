@if ($this->reabrirConfirmOrcamentoId !== null)
    @teleport('body')
        <div
            class="erp-lookup-modal erp-orc-reabrir-modal"
            wire:keydown.escape="cancelarReabrirOrcamento"
            wire:keydown.enter.prevent="confirmarReabrirOrcamento"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="cancelarReabrirOrcamento"></div>

            <div
                class="erp-orc-reabrir-modal__window"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="erp-orc-reabrir-title"
                aria-describedby="erp-orc-reabrir-desc"
            >
                <button
                    type="button"
                    class="erp-orc-reabrir-modal__close"
                    wire:click="cancelarReabrirOrcamento"
                    title="Cancelar"
                    aria-label="Cancelar"
                >✕</button>

                <div class="erp-orc-reabrir-modal__icon" aria-hidden="true">!</div>

                <h2 id="erp-orc-reabrir-title" class="erp-orc-reabrir-modal__title">
                    Orçamento fechado
                </h2>

                <p id="erp-orc-reabrir-desc" class="erp-orc-reabrir-modal__lead">
                    Este orçamento está fechado. Deseja reabri-lo para edição?
                </p>

                <div class="erp-orc-reabrir-modal__actions">
                    <button
                        type="button"
                        wire:click="confirmarReabrirOrcamento"
                        class="erp-orc-reabrir-modal__btn erp-orc-reabrir-modal__btn--primary"
                        id="erp-orc-reabrir-sim"
                    >
                        Sim
                    </button>
                    <button
                        type="button"
                        wire:click="cancelarReabrirOrcamento"
                        class="erp-orc-reabrir-modal__btn erp-orc-reabrir-modal__btn--ghost"
                        id="erp-orc-reabrir-nao"
                    >
                        Não
                    </button>
                </div>

                <p class="erp-orc-reabrir-modal__hint">Enter confirma · Esc cancela</p>
            </div>
        </div>
    @endteleport
@endif
