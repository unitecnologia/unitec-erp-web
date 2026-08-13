<div
    class="erp-cclass-trib-import-progress"
    wire:loading.class="is-visible"
    wire:target="cclassTribUpload"
    aria-live="polite"
    aria-busy="false"
    role="status"
    data-erp-cclass-trib-import-progress
>
    <div class="erp-cclass-trib-import-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-cclass-trib-import-progress__panel" wire:ignore data-erp-cclass-trib-import-progress-panel>
        <div class="erp-cclass-trib-import-progress__spinner" aria-hidden="true"></div>

        <p class="erp-cclass-trib-import-progress__title">Importando Classificação Tributária</p>

        <p class="erp-cclass-trib-import-progress__status" data-erp-cclass-trib-import-step-status>
            Recebendo arquivo…
        </p>

        <div class="erp-cclass-trib-import-progress__track" aria-hidden="true">
            <div class="erp-cclass-trib-import-progress__bar" data-erp-cclass-trib-import-step-bar></div>
        </div>

        <ol class="erp-cclass-trib-import-progress__steps">
            <li class="is-active" data-erp-cclass-trib-import-step>Recebendo arquivo</li>
            <li data-erp-cclass-trib-import-step>Validando layout do CSV</li>
            <li data-erp-cclass-trib-import-step>Importando classificações</li>
            <li data-erp-cclass-trib-import-step>Atualizando tabela na tela</li>
            <li data-erp-cclass-trib-import-step>Finalizando importação</li>
        </ol>

        <p class="erp-cclass-trib-import-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
