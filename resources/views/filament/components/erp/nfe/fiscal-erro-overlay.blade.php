{{-- Sempre no DOM: visibilidade via display (Livewire morph falha com atributo hidden). --}}
@php
    $nfeErroVisivel = filled($this->nfeFiscalOverlayTitulo);
@endphp
<div
    id="erp-nfe-fiscal-erro-overlay"
    class="erp-nfe-fiscal-overlay{{ $nfeErroVisivel ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-nfe-fiscal-overlay-title"
    aria-live="assertive"
    aria-hidden="{{ $nfeErroVisivel ? 'false' : 'true' }}"
    style="display: {{ $nfeErroVisivel ? 'grid' : 'none' }};"
    data-erp-nfe-fiscal-erro-overlay
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

        <h2 id="erp-nfe-fiscal-overlay-title" class="erp-nfe-fiscal-overlay__title">
            {{ $this->nfeFiscalOverlayTitulo }}
        </h2>

        <p
            class="erp-nfe-fiscal-overlay__codigo"
            style="display: {{ filled($this->nfeFiscalOverlayCodigo) ? 'block' : 'none' }};"
        >
            Código SEFAZ: <strong>{{ $this->nfeFiscalOverlayCodigo }}</strong>
        </p>

        <div
            class="erp-nfe-fiscal-overlay__text"
            style="display: {{ filled($this->nfeFiscalOverlayMensagem) ? 'block' : 'none' }};"
        >
            {!! nl2br(e((string) ($this->nfeFiscalOverlayMensagem ?? ''))) !!}
        </div>

        <p
            class="erp-nfe-fiscal-overlay__origem"
            style="display: {{ filled($this->nfeFiscalOverlayCodigo) ? 'block' : 'none' }};"
        >Esta é uma mensagem da SEFAZ — Santa Catarina.</p>

        <button
            type="button"
            wire:click="closeNfeFiscalOverlay"
            class="erp-nfe-fiscal-overlay__btn"
            id="erp-nfe-fiscal-overlay-entendido"
        >Entendido</button>

        <p class="erp-nfe-fiscal-overlay__hint">Clique em Entendido para continuar.</p>
    </div>
</div>
