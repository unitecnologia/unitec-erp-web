@if ($this->exclusaoConfirmOpen)
    <div
        class="erp-pagar-confirm-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarExcluirConta(); }
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarExcluirConta(); }
        "
    >
        <div class="erp-pagar-confirm-modal__backdrop" wire:click="cancelarExcluirConta"></div>

        <div class="erp-pagar-confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-pagar-exclusao-title">
            <div class="erp-pagar-confirm-modal__titlebar">
                <span id="erp-pagar-exclusao-title">Excluir conta a pagar</span>
                <button type="button" class="erp-pagar-confirm-modal__close" wire:click="cancelarExcluirConta" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-pagar-confirm-modal__body">
                <p class="erp-pagar-confirm-modal__message">
                    Tem certeza que deseja excluir esta conta?
                </p>
                @if (is_array($this->exclusaoContaResumo))
                    <dl class="erp-pagar-confirm-modal__resumo">
                        <div>
                            <dt>Fornecedor</dt>
                            <dd>{{ $this->exclusaoContaResumo['fornecedor'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Documento</dt>
                            <dd>{{ $this->exclusaoContaResumo['documento'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Valor</dt>
                            <dd>{{ $this->exclusaoContaResumo['valor'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Vencimento</dt>
                            <dd>{{ $this->exclusaoContaResumo['vencimento'] ?? '—' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <div class="erp-pagar-confirm-modal__actions">
                <button type="button" class="erp-pagar-confirm-modal__btn erp-pagar-confirm-modal__btn--yes" wire:click="confirmarExcluirConta">
                    Sim
                </button>
                <button type="button" class="erp-pagar-confirm-modal__btn erp-pagar-confirm-modal__btn--no" wire:click="cancelarExcluirConta">
                    Não
                </button>
            </div>
        </div>
    </div>
@endif
