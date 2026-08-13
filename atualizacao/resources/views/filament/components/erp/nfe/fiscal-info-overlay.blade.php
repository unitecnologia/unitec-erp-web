@if (filled($this->nfeFiscalInfoTitulo))
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--info"
        role="alertdialog"
        aria-labelledby="erp-nfe-fiscal-info-title"
        aria-live="polite"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">i</div>

            <h2 id="erp-nfe-fiscal-info-title" class="erp-nfe-fiscal-overlay__title">
                {{ $this->nfeFiscalInfoTitulo }}
            </h2>

            @if (filled($this->nfeFiscalInfoMensagem))
                <p class="erp-nfe-fiscal-overlay__text erp-nfe-fiscal-overlay__text--info">
                    {{ $this->nfeFiscalInfoMensagem }}
                </p>
            @endif

            <button
                type="button"
                wire:click="closeNfeFiscalInfoOverlay"
                class="erp-nfe-fiscal-overlay__btn"
                id="erp-nfe-fiscal-info-ok"
            >OK</button>
        </div>
    </div>
@endif
