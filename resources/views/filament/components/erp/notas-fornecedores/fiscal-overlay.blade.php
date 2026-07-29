@if (filled($this->nfFornFiscalOverlayTitulo))
    @php
        $tone = $this->nfFornFiscalOverlayTone ?? 'error';
        $toneClass = match ($tone) {
            'warning' => 'erp-nfe-fiscal-overlay--warning',
            'info' => 'erp-nfe-fiscal-overlay--info',
            default => '',
        };
    @endphp
    <div
        class="erp-nfe-fiscal-overlay {{ $toneClass }}"
        role="alertdialog"
        aria-labelledby="erp-nf-forn-fiscal-overlay-title"
        aria-live="assertive"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

            <h2 id="erp-nf-forn-fiscal-overlay-title" class="erp-nfe-fiscal-overlay__title">
                {{ $this->nfFornFiscalOverlayTitulo }}
            </h2>

            @if (filled($this->nfFornFiscalOverlayCodigo))
                <p class="erp-nfe-fiscal-overlay__codigo">
                    Código SEFAZ: <strong>{{ $this->nfFornFiscalOverlayCodigo }}</strong>
                </p>
            @endif

            @if (filled($this->nfFornFiscalOverlayMensagem))
                <div class="erp-nfe-fiscal-overlay__text">
                    {!! nl2br(e($this->nfFornFiscalOverlayMensagem)) !!}
                </div>
            @endif

            <p class="erp-nfe-fiscal-overlay__origem">Esta é uma mensagem da SEFAZ — {{ $this->nfFornFiscalOverlayOrigem ?? 'Distribuição DF-e' }}.</p>

            <button
                type="button"
                wire:click="closeNfFornFiscalOverlay"
                class="erp-nfe-fiscal-overlay__btn"
                id="erp-nf-forn-fiscal-overlay-entendido"
            >Entendido</button>

            <p class="erp-nfe-fiscal-overlay__hint">Clique em Entendido para continuar.</p>
        </div>
    </div>
@endif
