@if (filled($this->nfeFiscalOverlayTitulo))
    <div
        class="erp-nfe-fiscal-overlay"
        role="alertdialog"
        aria-labelledby="erp-nfe-fiscal-overlay-title"
        aria-live="assertive"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

            <h2 id="erp-nfe-fiscal-overlay-title" class="erp-nfe-fiscal-overlay__title">
                {{ $this->nfeFiscalOverlayTitulo }}
            </h2>

            @if (filled($this->nfeFiscalOverlayCodigo))
                <p class="erp-nfe-fiscal-overlay__codigo">
                    Código SEFAZ: <strong>{{ $this->nfeFiscalOverlayCodigo }}</strong>
                </p>
            @endif

            @if (filled($this->nfeFiscalOverlayMensagem))
                <div class="erp-nfe-fiscal-overlay__text">
                    {!! nl2br(e($this->nfeFiscalOverlayMensagem)) !!}
                </div>
            @endif

            <p class="erp-nfe-fiscal-overlay__origem">Esta é uma mensagem da SEFAZ — Santa Catarina.</p>

            <button
                type="button"
                wire:click="closeNfeFiscalOverlay"
                class="erp-nfe-fiscal-overlay__btn"
                id="erp-nfe-fiscal-overlay-entendido"
            >Entendido</button>

            <p class="erp-nfe-fiscal-overlay__hint">Clique em Entendido para continuar.</p>
        </div>
    </div>
@endif
