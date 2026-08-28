<div
    class="erp-pdv-fiscal-progress"
    aria-live="polite"
    aria-busy="false"
    role="status"
    data-erp-pdv-fiscal-progress
>
    <div class="erp-pdv-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-pdv-fiscal-progress__panel" wire:ignore data-erp-pdv-fiscal-progress-panel>
        <div class="erp-pdv-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-pdv-fiscal-progress__title">Transmitindo NFC-e</p>

        <p class="erp-pdv-fiscal-progress__status" data-erp-pdv-fiscal-step-status>
            Validando dados da NFC-e…
        </p>

        <div class="erp-pdv-fiscal-progress__track" aria-hidden="true">
            <div class="erp-pdv-fiscal-progress__bar" data-erp-pdv-fiscal-step-bar></div>
        </div>

        <ol class="erp-pdv-fiscal-progress__steps">
            <li class="is-active" data-erp-pdv-fiscal-step data-step="0">Validando dados da NFC-e</li>
            <li data-erp-pdv-fiscal-step data-step="1">Montando XML do documento</li>
            <li data-erp-pdv-fiscal-step data-step="2">Assinando digitalmente</li>
            <li data-erp-pdv-fiscal-step data-step="3">Enviando à SEFAZ (aguardando resposta)</li>
            <li data-erp-pdv-fiscal-step data-step="4">Processando autorização</li>
        </ol>

        <p class="erp-pdv-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>

    <div
        id="erp-pdv-fiscal-progress-stream"
        wire:stream="erpPdvFiscalProgress"
        hidden
        aria-hidden="true"
        data-erp-pdv-fiscal-progress-stream
    ></div>
</div>
