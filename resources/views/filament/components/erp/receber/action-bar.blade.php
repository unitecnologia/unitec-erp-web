<div class="erp-receber-actions">
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
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-receber-actions__btn" data-erp-key="F4">
        <span class="erp-receber-actions__icon">🖨</span>
        <span class="erp-receber-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-receber-actions__btn" data-erp-key="F5">
        <span class="erp-receber-actions__icon">↻</span>
        <span class="erp-receber-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="baixarConta" class="erp-receber-actions__btn erp-receber-actions__btn--baixar" data-erp-key="F8">
        <span class="erp-receber-actions__icon erp-receber-actions__icon--baixar">↓</span>
        <span class="erp-receber-actions__label"><kbd>F8</kbd> | Baixar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-receber-actions__btn erp-receber-actions__btn--close">
        <span class="erp-receber-actions__icon erp-receber-actions__icon--close">✕</span>
        <span class="erp-receber-actions__label">Fechar</span>
    </button>
</div>
