@if ($this->osNfceEmitirModalOpen)
    <div
        class="erp-lookup-modal erp-os-nfce-emitir-modal"
        wire:keydown.escape.window="cancelarOsNfceEmitirModal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-os-nfce-emitir-cpf')?.focus())"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelarOsNfceEmitirModal"></div>

        <div
            class="erp-lookup-modal__window erp-os-nfce-emitir-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-os-nfce-emitir-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-nfce-emitir-title">NFC-e Peças — Consumidor</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="cancelarOsNfceEmitirModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-os-nfce-emitir-modal__body">
                <p class="erp-os-nfce-emitir-modal__hint">
                    Informe CPF e nome se quiser identificar o consumidor. Deixe em branco para consumidor não identificado.
                    NFC-e não aceita CNPJ — para pessoa jurídica use F10 | NF-e Peças.
                </p>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-cpf">CPF (opcional)</label>
                    <input
                        id="erp-os-nfce-emitir-cpf"
                        type="text"
                        class="erp-os-nfce-emitir-modal__input"
                        wire:model="osNfceEmitirCpf"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        data-mask="cpf"
                    >
                </div>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-nome">Nome (opcional)</label>
                    <input
                        id="erp-os-nfce-emitir-nome"
                        type="text"
                        class="erp-os-nfce-emitir-modal__input"
                        wire:model="osNfceEmitirNome"
                        autocomplete="off"
                        maxlength="60"
                        placeholder="CONSUMIDOR ou nome completo"
                    >
                </div>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-obs">Observações da NFC-e (opcional)</label>
                    <textarea
                        id="erp-os-nfce-emitir-obs"
                        class="erp-os-nfce-emitir-modal__textarea"
                        wire:model="osNfceEmitirObservacoes"
                        rows="3"
                        maxlength="500"
                        placeholder="Informações adicionais (infCpl)"
                    ></textarea>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-os-nfce-emitir-modal__actions">
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                    wire:click="confirmarOsNfceEmitirModal"
                    wire:loading.attr="disabled"
                    wire:target="confirmarOsNfceEmitirModal"
                >
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="confirmarOsNfceEmitirModal">Transmitir</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="confirmarOsNfceEmitirModal">Transmitindo…</span>
                </button>
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                    data-erp-key="Escape"
                    wire:click="cancelarOsNfceEmitirModal"
                    wire:loading.attr="disabled"
                    wire:target="confirmarOsNfceEmitirModal"
                >
                    <span class="erp-pcad-actions__label">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
