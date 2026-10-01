{{-- Mensagem padrão central (mesmo padrão NF-e / vencimento boleto). --}}
@php
    $formaBloqueioVisivel = (bool) $this->boletoFormaBloqueioOpen
        && filled($this->boletoFormaBloqueioTitulo);
@endphp
<div
    id="erp-receber-boleto-forma-bloqueio-overlay"
    class="erp-nfe-fiscal-overlay erp-receber-boleto-venc-erro{{ $formaBloqueioVisivel ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-receber-boleto-forma-bloqueio-title"
    aria-live="assertive"
    aria-hidden="{{ $formaBloqueioVisivel ? 'false' : 'true' }}"
    style="display: {{ $formaBloqueioVisivel ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

        <h2 id="erp-receber-boleto-forma-bloqueio-title" class="erp-nfe-fiscal-overlay__title">
            {{ $this->boletoFormaBloqueioTitulo }}
        </h2>

        <div
            class="erp-nfe-fiscal-overlay__text"
            style="display: {{ filled($this->boletoFormaBloqueioMensagem) ? 'block' : 'none' }};"
        >
            {!! nl2br(e((string) ($this->boletoFormaBloqueioMensagem ?? ''))) !!}
        </div>

        <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
            <button
                type="button"
                wire:click="acknowledgeBoletoFormaBloqueio"
                class="erp-nfe-fiscal-overlay__btn"
            >Alterar conta</button>

            <button
                type="button"
                wire:click="closeBoletoFormaBloqueio"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
            >Fechar</button>
        </div>

        <p class="erp-nfe-fiscal-overlay__hint">Mude o tipo para Boleto e gere novamente.</p>
    </div>
</div>
