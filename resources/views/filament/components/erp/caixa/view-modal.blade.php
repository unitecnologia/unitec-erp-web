@if ($this->viewModalOpen)
    <div
        class="erp-lookup-modal erp-caixa-view-modal"
        wire:keydown.escape.window="closeCaixaView"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCaixaView"></div>

        <div
            class="erp-lookup-modal__window erp-caixa-view-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-caixa-view-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-caixa-view-modal-title">Lançamento de Caixa</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeCaixaView"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-caixa-view-modal__body">
                <div class="erp-caixa-view-modal__grid">
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Código</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['codigo'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Emissão</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['emissao'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Documento</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['documento'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field erp-caixa-view-modal__field--wide">
                        <span class="erp-caixa-view-modal__label">Histórico</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['historico'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Plano de Contas</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['plano_contas'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Conta</span>
                        <span class="erp-caixa-view-modal__value">{{ $this->viewModalData['conta'] ?? '—' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Entrada</span>
                        <span class="erp-caixa-view-modal__value erp-caixa-view-modal__value--entrada">{{ $this->viewModalData['entrada'] ?? '0,00' }}</span>
                    </div>
                    <div class="erp-caixa-view-modal__field">
                        <span class="erp-caixa-view-modal__label">Saída</span>
                        <span class="erp-caixa-view-modal__value erp-caixa-view-modal__value--saida">{{ $this->viewModalData['saida'] ?? '0,00' }}</span>
                    </div>
                </div>

                <div class="erp-pcad-actions erp-caixa-view-modal__actions">
                    <button type="button" wire:click="closeCaixaView" class="erp-pcad-actions__btn" data-erp-key="Escape">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
