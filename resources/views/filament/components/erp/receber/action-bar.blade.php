<div class="erp-receber-actions">
    @if ($this->viewTab === 'desdobramentos')
        <button type="button" wire:click="pedirEstornoDesdobramento" class="erp-receber-actions__btn erp-receber-actions__btn--estorno" data-erp-key="F11">
            <span class="erp-receber-actions__icon erp-receber-actions__icon--estorno">↩</span>
            <span class="erp-receber-actions__label"><kbd>F11</kbd> | Estornar</span>
        </button>
        <button type="button" wire:click="voltarParaTitulos" class="erp-receber-actions__btn">
            <span class="erp-receber-actions__icon">←</span>
            <span class="erp-receber-actions__label">Voltar aos Títulos</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-receber-actions__btn erp-receber-actions__btn--close">
            <span class="erp-receber-actions__icon erp-receber-actions__icon--close">✕</span>
            <span class="erp-receber-actions__label">Fechar</span>
        </button>
    @else
    <button type="button" wire:click="createConta" class="erp-receber-actions__btn" data-erp-key="F2">
        <span class="erp-receber-actions__icon erp-receber-actions__icon--new">+</span>
        <span class="erp-receber-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editConta" class="erp-receber-actions__btn" data-erp-key="F3">
        <span class="erp-receber-actions__icon">✎</span>
        <span class="erp-receber-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button
        type="button"
        wire:click="deleteConta"
        class="erp-receber-actions__btn"
        data-erp-key="Delete"
        @disabled(! $this->podeExcluirContaDestacada)
        title="{{ $this->exclusaoContaTooltip }}"
    >
        <span class="erp-receber-actions__icon erp-receber-actions__icon--cancel">✕</span>
        <span class="erp-receber-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button
        type="button"
        wire:click="printContasReceber"
        class="erp-receber-actions__btn"
        data-erp-key="F4"
        title="Relatório de contas a receber"
    >
        <span class="erp-receber-actions__icon">🖨</span>
        <span class="erp-receber-actions__label"><kbd>F4</kbd> | Relatório</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-receber-actions__btn" data-erp-key="F5">
        <span class="erp-receber-actions__icon">↻</span>
        <span class="erp-receber-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="baixarConta" class="erp-receber-actions__btn erp-receber-actions__btn--baixar" data-erp-key="F8">
        <span class="erp-receber-actions__icon erp-receber-actions__icon--baixar">↓</span>
        <span class="erp-receber-actions__label"><kbd>F8</kbd> | Baixar</span>
    </button>
    <button
        type="button"
        wire:click="gerarBoleto"
        wire:loading.attr="disabled"
        wire:target="gerarBoleto,emitirBoletoApi,confirmarGerarBoletoComConta"
        class="erp-receber-actions__btn"
        title="Gerar boleto via API Ailos (conta selecionada)"
    >
        <span class="erp-receber-actions__icon" wire:loading.remove wire:target="emitirBoletoApi,confirmarGerarBoletoComConta">≡</span>
        <span class="erp-receber-actions__icon" wire:loading wire:target="emitirBoletoApi,confirmarGerarBoletoComConta">…</span>
        <span class="erp-receber-actions__label" wire:loading.remove wire:target="emitirBoletoApi,confirmarGerarBoletoComConta">Gerar Boleto</span>
        <span class="erp-receber-actions__label" wire:loading wire:target="emitirBoletoApi,confirmarGerarBoletoComConta">Gerando…</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-receber-actions__btn erp-receber-actions__btn--close">
        <span class="erp-receber-actions__icon erp-receber-actions__icon--close">✕</span>
        <span class="erp-receber-actions__label">Fechar</span>
    </button>
    @endif
</div>
