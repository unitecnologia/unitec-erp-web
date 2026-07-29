@if ($this->estornoConfirmOpen)
    <div
        class="erp-pagar-confirm-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarEstornoDesdobramento(); }
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarEstornoDesdobramento(); }
        "
    >
        <div class="erp-pagar-confirm-modal__backdrop" wire:click="cancelarEstornoDesdobramento"></div>

        <div class="erp-pagar-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-pagar-estorno-title">
            <div class="erp-pagar-confirm-modal__titlebar">
                <span id="erp-pagar-estorno-title">Confirmação</span>
                <button type="button" class="erp-pagar-confirm-modal__close" wire:click="cancelarEstornoDesdobramento" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-pagar-confirm-modal__body">
                <p class="erp-pagar-confirm-modal__message">
                    {{ count($this->desdobramentoSelectedIds) > 1
                        ? 'Tem certeza que deseja ESTORNAR as parcelas pagas selecionadas?'
                        : 'Tem certeza que deseja ESTORNAR a parcela paga selecionada?' }}
                </p>
                @if (count($this->desdobramentoSelectedIds) > 0)
                    <p class="erp-pagar-confirm-modal__detail">
                        {{ count($this->desdobramentoSelectedIds) === 1
                            ? '1 parcela selecionada'
                            : count($this->desdobramentoSelectedIds).' parcelas selecionadas' }}
                    </p>
                @endif
            </div>

            <div class="erp-pagar-confirm-modal__actions">
                <button type="button" class="erp-pagar-confirm-modal__btn erp-pagar-confirm-modal__btn--yes" wire:click="confirmarEstornoDesdobramento">
                    Sim
                </button>
                <button type="button" class="erp-pagar-confirm-modal__btn erp-pagar-confirm-modal__btn--no" wire:click="cancelarEstornoDesdobramento">
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
