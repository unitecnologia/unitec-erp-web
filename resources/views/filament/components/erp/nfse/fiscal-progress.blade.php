<div
    class="erp-nfe-fiscal-progress"
    wire:loading.class="is-visible"
    wire:target="transmitirNfse,confirmarTransmissaoProducao"
    aria-live="polite"
    aria-busy="false"
    role="status"
    data-erp-nfse-fiscal-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-nfse-fiscal-progress-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Transmitindo NFS-e</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-nfse-fiscal-step-status>
            Validando dados da NFS-e…
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-nfse-fiscal-step-bar></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            <li class="is-active" data-erp-nfse-fiscal-step data-step="0">Validando dados da NFS-e</li>
            <li data-erp-nfse-fiscal-step data-step="1">Montando XML do documento</li>
            <li data-erp-nfse-fiscal-step data-step="2">Assinando digitalmente</li>
            <li data-erp-nfse-fiscal-step data-step="3">Enviando à SEFIN (aguardando resposta)</li>
            <li data-erp-nfse-fiscal-step data-step="4">Processando autorização</li>
        </ol>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
