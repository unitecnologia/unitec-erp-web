{{-- Sempre no DOM: visibilidade via display (Livewire morph). --}}
@php
    $vencSucessoVisivel = (bool) $this->contaBoletoVencimentoSucessoOpen
        && filled($this->contaBoletoVencimentoSucessoDetalhe);
@endphp
<div
    id="erp-receber-boleto-vencimento-sucesso-overlay"
    class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso{{ $vencSucessoVisivel ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-receber-boleto-vencimento-sucesso-title"
    aria-live="polite"
    aria-hidden="{{ $vencSucessoVisivel ? 'false' : 'true' }}"
    style="display: {{ $vencSucessoVisivel ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>

        <h2 id="erp-receber-boleto-vencimento-sucesso-title" class="erp-nfe-fiscal-overlay__title">
            VENCIMENTO ATUALIZADO NO BANCO
        </h2>

        <p class="erp-nfe-fiscal-overlay__codigo">{{ $this->contaBoletoVencimentoSucessoDetalhe }}</p>

        <div class="erp-nfe-fiscal-overlay__actions">
            <button
                type="button"
                wire:click="acknowledgeContaBoletoVencimentoSucesso"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
            >OK</button>
        </div>

        <p class="erp-nfe-fiscal-overlay__hint">Clique em OK para voltar à lista.</p>
    </div>
</div>
