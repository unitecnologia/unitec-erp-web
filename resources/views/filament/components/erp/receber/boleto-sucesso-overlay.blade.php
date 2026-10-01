{{-- Sempre no DOM: visibilidade via display (Livewire morph). --}}
@php
    $boletoSucessoVisivel = filled($this->boletoSucessoDetalhe)
        && ! $this->boletoEnviarModalOpen;
    $boletoSegundaVia = (bool) ($this->boletoSucessoSegundaVia ?? false);
@endphp
<div
    id="erp-receber-boleto-sucesso-overlay"
    class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso{{ $boletoSucessoVisivel ? ' is-visible' : '' }}{{ $boletoSegundaVia ? ' erp-receber-boleto-sucesso--segunda-via' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-receber-boleto-sucesso-title"
    aria-live="polite"
    aria-hidden="{{ $boletoSucessoVisivel ? 'false' : 'true' }}"
    style="display: {{ $boletoSucessoVisivel ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>

        @if ($boletoSegundaVia)
            <p class="erp-receber-boleto-sucesso__badge">Segunda via</p>
            <h2 id="erp-receber-boleto-sucesso-title" class="erp-nfe-fiscal-overlay__title">
                BOLETO JÁ EMITIDO
            </h2>
        @else
            <h2 id="erp-receber-boleto-sucesso-title" class="erp-nfe-fiscal-overlay__title">
                BOLETO GERADO COM SUCESSO
            </h2>
        @endif

        <p class="erp-nfe-fiscal-overlay__codigo">{{ $this->boletoSucessoDetalhe }}</p>

        <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
            <button
                type="button"
                wire:click="printBoletoSucesso"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print"
            >Imprimir</button>

            <button
                type="button"
                wire:click="openBoletoEnviarModal"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email"
            >Enviar</button>

            <button
                type="button"
                wire:click="acknowledgeBoletoSucessoOverlay"
                class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
            >Sair</button>
        </div>

        <p class="erp-nfe-fiscal-overlay__hint">
            @if ($boletoSegundaVia)
                Este boleto já estava registrado no banco. Esta é a segunda via para impressão ou envio.
            @else
                O título já está registrado no banco. Você pode imprimir ou enviar ao cliente.
            @endif
        </p>
    </div>
</div>
