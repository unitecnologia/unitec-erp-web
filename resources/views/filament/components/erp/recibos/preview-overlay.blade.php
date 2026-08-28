@if ($this->previewOverlayOpen && filled($this->previewOverlayUrl))
    <div
        class="erp-form-overlay erp-recibo-preview-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar recibo"
        x-data
        x-on:keydown.escape.window="$wire.closePreviewOverlay()"
        x-init="
            const onMessage = (event) => {
                if (event.data?.type === 'erp-recibo-preview-close') {
                    $wire.closePreviewOverlay();
                }
            };
            window.addEventListener('message', onMessage);
            return () => window.removeEventListener('message', onMessage);
        "
    >
        <div class="erp-form-overlay__backdrop" wire:click="closePreviewOverlay"></div>

        <div class="erp-form-overlay__panel">
            <iframe
                src="{{ $this->previewOverlayUrl }}"
                class="erp-form-overlay__iframe"
                title="Visualizar recibo"
            ></iframe>
        </div>
    </div>
@endif
