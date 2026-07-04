<div class="erp-caixa-actions">
    <button type="button" wire:click="createLancamento" class="erp-caixa-actions__btn" data-erp-key="F2">
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--new">+</span>
        <span class="erp-caixa-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editLancamento" class="erp-caixa-actions__btn" data-erp-key="F3">
        <span class="erp-caixa-actions__icon">✎</span>
        <span class="erp-caixa-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteLancamento" class="erp-caixa-actions__btn" data-erp-key="Delete">
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--cancel">✕</span>
        <span class="erp-caixa-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-caixa-actions__btn" data-erp-key="F5">
        <span class="erp-caixa-actions__icon">↻</span>
        <span class="erp-caixa-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-caixa-actions__btn" data-erp-key="F6">
        <span class="erp-caixa-actions__icon">🖨</span>
        <span class="erp-caixa-actions__label"><kbd>F6</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-caixa-actions__btn erp-caixa-actions__btn--close">
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--close">✕</span>
        <span class="erp-caixa-actions__label">Fechar</span>
    </button>
</div>
