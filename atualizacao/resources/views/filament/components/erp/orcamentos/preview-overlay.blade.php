@if ($this->previewOverlayOpen && filled($this->previewOverlayUrl))
    <div
        class="erp-form-overlay erp-orc-preview-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar orçamento"
        data-livewire-id="{{ $this->getId() }}"
    >
        <div class="erp-form-overlay__backdrop" wire:click="closePreviewOverlay"></div>

        <div class="erp-form-overlay__panel">
            <iframe
                src="{{ $this->previewOverlayUrl }}"
                class="erp-form-overlay__iframe"
                title="Visualizar orçamento"
                data-erp-orcamento-preview-iframe
            ></iframe>
        </div>
    </div>
@endif
