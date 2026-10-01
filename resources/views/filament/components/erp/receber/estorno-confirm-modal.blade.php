@if ($this->estornoConfirmOpen)
    <div
        class="erp-receber-confirm-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarEstornoDesdobramento(); }
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarEstornoDesdobramento(); }
        "
    >
        <div class="erp-receber-confirm-modal__backdrop" wire:click="cancelarEstornoDesdobramento"></div>

        <div class="erp-receber-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-receber-estorno-title">
            <div class="erp-receber-confirm-modal__titlebar">
                <span id="erp-receber-estorno-title">Confirmação</span>
                <button type="button" class="erp-receber-confirm-modal__close" wire:click="cancelarEstornoDesdobramento" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-receber-confirm-modal__body">
                <p class="erp-receber-confirm-modal__message">
                    {{ count($this->desdobramentoSelectedIds) > 1
                        ? 'Tem certeza que deseja ESTORNAR as baixas selecionadas?'
                        : 'Tem certeza que deseja ESTORNAR a baixa selecionada?' }}
                </p>
                @if (count($this->desdobramentoSelectedIds) > 0)
                    <p class="erp-receber-confirm-modal__detail">
                        {{ count($this->desdobramentoSelectedIds) === 1
                            ? '1 baixa selecionada'
                            : count($this->desdobramentoSelectedIds).' baixas selecionadas' }}
                    </p>
                @endif
            </div>

            <div class="erp-receber-confirm-modal__actions">
                <button type="button" class="erp-receber-confirm-modal__btn erp-receber-confirm-modal__btn--yes" wire:click="confirmarEstornoDesdobramento">
                    Sim
                </button>
                <button type="button" class="erp-receber-confirm-modal__btn erp-receber-confirm-modal__btn--no" wire:click="cancelarEstornoDesdobramento">
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
