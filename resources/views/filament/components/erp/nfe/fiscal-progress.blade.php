<div

    class="erp-nfe-fiscal-progress"

    wire:loading.class="is-visible"

    wire:target="transmitNfe"

    aria-live="polite"

    aria-busy="false"

    role="status"

    data-erp-nfe-fiscal-progress

>

    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>



    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-nfe-fiscal-progress-panel>

        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>



        <p class="erp-nfe-fiscal-progress__title">Transmitindo NF-e</p>



        <p class="erp-nfe-fiscal-progress__status" data-erp-nfe-fiscal-step-status>

            Validando dados da NF-e…

        </p>



        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">

            <div class="erp-nfe-fiscal-progress__bar" data-erp-nfe-fiscal-step-bar></div>

        </div>



        <ol class="erp-nfe-fiscal-progress__steps">

            <li class="is-active" data-erp-nfe-fiscal-step>Validando dados da NF-e</li>

            <li data-erp-nfe-fiscal-step>Montando XML do documento</li>

            <li data-erp-nfe-fiscal-step>Assinando digitalmente</li>

            <li data-erp-nfe-fiscal-step>Enviando à SEFAZ</li>

            <li data-erp-nfe-fiscal-step>Processando autorização</li>

        </ol>



        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>

    </div>

</div>

