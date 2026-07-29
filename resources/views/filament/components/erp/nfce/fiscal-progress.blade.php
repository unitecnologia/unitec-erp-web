<div
    class="erp-nfce-fiscal-progress"
    wire:loading.class="is-visible"
    wire:target="transmitirNfce"
    aria-live="polite"
    aria-busy="true"
    role="status"
>
    <div class="erp-nfce-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfce-fiscal-progress__panel">
        <p class="erp-nfce-fiscal-progress__status">
            Transmitindo NFC-e à SEFAZ…
        </p>

        <div class="erp-nfce-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfce-fiscal-progress__bar"></div>
        </div>

        <p class="erp-nfce-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
