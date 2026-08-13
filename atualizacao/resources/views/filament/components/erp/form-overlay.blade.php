<div class="erp-form-overlay" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div
        class="erp-form-overlay__backdrop"
        wire:click="{{ $closeAction }}"
    ></div>

    <div class="erp-form-overlay__panel">
        <iframe
            src="{{ $iframeUrl }}"
            class="erp-form-overlay__iframe"
            title="{{ $title }}"
            data-erp-form-overlay-iframe
        ></iframe>
    </div>
</div>
