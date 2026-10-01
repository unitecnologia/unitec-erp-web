@if ($this->previewOverlayOpen && filled($this->previewOverlayUrl))
    <div
        class="erp-form-overlay erp-os-preview-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar ordem de serviço"
        data-livewire-id="{{ $this->getId() }}"
    >
        <div class="erp-form-overlay__backdrop" wire:click="closePreviewOverlay"></div>

        <div class="erp-form-overlay__panel">
            <iframe
                src="{{ $this->previewOverlayUrl }}"
                class="erp-form-overlay__iframe"
                title="Visualizar ordem de serviço"
                data-erp-os-preview-iframe
            ></iframe>
        </div>
    </div>
@endif
