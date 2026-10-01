{{-- Sempre no DOM: visibilidade via display (Livewire morph). --}}
@php
    $vencErroVisivel = (bool) $this->contaBoletoVencimentoErroOpen
        && filled($this->contaBoletoVencimentoErroTitulo);
@endphp
<div
    id="erp-receber-boleto-vencimento-erro-overlay"
    class="erp-nfe-fiscal-overlay erp-receber-boleto-venc-erro{{ $vencErroVisivel ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-receber-boleto-vencimento-erro-title"
    aria-live="assertive"
    aria-hidden="{{ $vencErroVisivel ? 'false' : 'true' }}"
    style="display: {{ $vencErroVisivel ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

        <h2 id="erp-receber-boleto-vencimento-erro-title" class="erp-nfe-fiscal-overlay__title">
            {{ $this->contaBoletoVencimentoErroTitulo }}
        </h2>

        <div
            class="erp-nfe-fiscal-overlay__text"
            style="display: {{ filled($this->contaBoletoVencimentoErroMensagem) ? 'block' : 'none' }};"
        >
            {!! nl2br(e((string) ($this->contaBoletoVencimentoErroMensagem ?? ''))) !!}
        </div>

        <button
            type="button"
            wire:click="closeContaBoletoVencimentoErro"
            class="erp-nfe-fiscal-overlay__btn"
        >Entendido</button>

        <p class="erp-nfe-fiscal-overlay__hint">A conta não foi salva. Corrija e tente novamente.</p>
    </div>
</div>
