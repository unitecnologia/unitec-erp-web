@php
    // Sem parâmetros = finalização do PDV. Com $fiscalProgressOperacao = mesmo overlay para um
    // comando SEFAZ de outra tela do ERP (aberto/fechado por erp-sefaz-progress.js).
    $fiscalProgressOperacao ??= null;
    $fiscalProgressTitulo ??= 'Transmitindo NFC-e';
    $fiscalProgressEtapas ??= [
        'Validando dados da NFC-e',
        'Montando XML do documento',
        'Assinando digitalmente',
        'Enviando à SEFAZ',
        'Processando autorização',
    ];
@endphp
<div
    class="erp-pdv-fiscal-progress"
    aria-live="polite"
    aria-busy="false"
    role="status"
    @if ($fiscalProgressOperacao)
        data-erp-sefaz-progress="{{ $fiscalProgressOperacao }}"
    @else
        data-erp-pdv-fiscal-progress
    @endif
>
    <div class="erp-pdv-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-pdv-fiscal-progress__panel" wire:ignore data-erp-pdv-fiscal-progress-panel>
        <div class="erp-pdv-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-pdv-fiscal-progress__title">{{ $fiscalProgressTitulo }}</p>

        <p class="erp-pdv-fiscal-progress__status" data-erp-pdv-fiscal-step-status>
            {{ array_values($fiscalProgressEtapas)[0] ?? '' }}…
        </p>

        <div class="erp-pdv-fiscal-progress__track" aria-hidden="true">
            <div class="erp-pdv-fiscal-progress__bar" data-erp-pdv-fiscal-step-bar></div>
        </div>

        <ol class="erp-pdv-fiscal-progress__steps">
            @foreach ($fiscalProgressEtapas as $chave => $etapa)
                <li @if ($loop->first) class="is-active" @endif data-erp-pdv-fiscal-step @if (is_string($chave)) data-etapa="{{ $chave }}" @endif>{{ $etapa }}</li>
            @endforeach
        </ol>

        <p class="erp-pdv-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
