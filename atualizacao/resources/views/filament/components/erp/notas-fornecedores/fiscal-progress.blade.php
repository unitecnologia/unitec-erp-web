<div
    class="erp-nfe-fiscal-progress erp-nf-forn-fiscal-progress"
    wire:loading.class="is-visible"
    wire:target="consultarLote"
    aria-live="polite"
    aria-busy="false"
    role="status"
    data-erp-nf-forn-fiscal-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-nf-forn-fiscal-progress-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Consultando lote DF-e</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-nf-forn-fiscal-step-status>
            Validando certificado e empresa…
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            <div class="erp-nfe-fiscal-progress__bar" data-erp-nf-forn-fiscal-step-bar style="width: 12%;"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            <li class="is-active" data-erp-nf-forn-fiscal-step>Validando certificado e empresa</li>
            <li data-erp-nf-forn-fiscal-step>Conectando à Distribuição DF-e</li>
            <li data-erp-nf-forn-fiscal-step>Consultando lote na SEFAZ</li>
            <li data-erp-nf-forn-fiscal-step>Processando documentos retornados</li>
            <li data-erp-nf-forn-fiscal-step>Atualizando notas de fornecedores</li>
        </ol>

        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>

<div
    class="erp-nfe-fiscal-progress erp-nf-forn-fiscal-progress"
    wire:loading.class="is-visible"
    wire:target="openNotaFornecedorVisualizar,openNotaFornecedorVisualizarSelecionada"
    aria-live="polite"
    role="status"
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>
    <div class="erp-nfe-fiscal-progress__panel">
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>
        <p class="erp-nfe-fiscal-progress__title">Carregando DANFE</p>
        <p class="erp-nfe-fiscal-progress__status">Baixando XML completo na SEFAZ…</p>
        <p class="erp-nfe-fiscal-progress__hint">Aguarde, não feche esta tela.</p>
    </div>
</div>
