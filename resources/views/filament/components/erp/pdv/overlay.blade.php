<div class="erp-pdv-overlay" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div
        class="erp-pdv-overlay__backdrop"
        wire:click="{{ $type === 'product' ? 'closeProductOverlay' : 'closePersonOverlay' }}"
    ></div>

    <div class="erp-pdv-overlay__panel">
        <div class="erp-pdv-overlay__header">
            <span>{{ $title }}</span>
            <button
                type="button"
                class="erp-pdv-overlay__close"
                wire:click="{{ $type === 'product' ? 'closeProductOverlay' : 'closePersonOverlay' }}"
                title="Fechar (ESC)"
            >
                ✕
            </button>
        </div>

        <iframe
            src="{{ $iframeUrl }}"
            class="erp-pdv-overlay__iframe"
            title="{{ $title }}"
            data-erp-pdv-overlay-iframe
        ></iframe>
    </div>
</div>
