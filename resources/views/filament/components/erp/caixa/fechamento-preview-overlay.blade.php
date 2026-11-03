@if ($this->caixaFecharPreviewOpen && filled($this->caixaFecharPreviewUrl))
    <div
        class="erp-form-overlay erp-caixa-fechamento-preview-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar fechamento de caixa"
        x-data
        x-on:keydown.escape.window="$wire.closeCaixaFechamentoPreview()"
        x-init="
            const onMessage = (event) => {
                if (event.data?.type === 'erp-caixa-fechamento-preview-close') {
                    $wire.closeCaixaFechamentoPreview();
                }
            };
            window.addEventListener('message', onMessage);
            return () => window.removeEventListener('message', onMessage);
        "
    >
        <div class="erp-form-overlay__backdrop" wire:click="closeCaixaFechamentoPreview"></div>
        <div class="erp-form-overlay__panel">
            <iframe
                src="{{ $this->caixaFecharPreviewUrl }}"
                class="erp-form-overlay__iframe"
                title="Fechamento de caixa"
            ></iframe>
        </div>
    </div>
@endif
