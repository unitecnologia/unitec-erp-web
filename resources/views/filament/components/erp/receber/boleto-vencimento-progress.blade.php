@php
    $banco = filled($this->contaFormBoletoBancoNome) ? $this->contaFormBoletoBancoNome : 'banco';
@endphp
{{-- Controle via JS (etapas cronometradas). wire:ignore evita o morph matar a animação. --}}
<div
    class="erp-receber-boleto-venc-progress"
    aria-live="polite"
    aria-busy="false"
    role="status"
    data-erp-receber-boleto-venc-progress
    wire:ignore
>
    <div class="erp-receber-boleto-venc-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-receber-boleto-venc-progress__panel">
        <div class="erp-receber-boleto-venc-progress__spinner" aria-hidden="true"></div>

        <p class="erp-receber-boleto-venc-progress__title">Atualizando vencimento do boleto</p>

        <p class="erp-receber-boleto-venc-progress__status" data-erp-receber-boleto-venc-status>
            Validando conta…
        </p>

        <div class="erp-receber-boleto-venc-progress__track" aria-hidden="true">
            <div class="erp-receber-boleto-venc-progress__bar" data-erp-receber-boleto-venc-bar></div>
        </div>

        <ol class="erp-receber-boleto-venc-progress__steps">
            <li class="is-active" data-erp-receber-boleto-venc-step data-step="0">Validando conta</li>
            <li data-erp-receber-boleto-venc-step data-step="1">Conectando ao {{ $banco }}</li>
            <li data-erp-receber-boleto-venc-step data-step="2">Enviando instrução de vencimento</li>
            <li data-erp-receber-boleto-venc-step data-step="3">Confirmando no banco</li>
        </ol>

        <p class="erp-receber-boleto-venc-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
