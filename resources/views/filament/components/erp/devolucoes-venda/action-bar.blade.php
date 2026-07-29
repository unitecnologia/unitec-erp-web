<div class="erp-orcamentos-actions">
    <button type="button" wire:click="createDevolucao" class="erp-orcamentos-actions__btn" data-erp-key="F2">
        <span class="erp-orcamentos-actions__icon erp-orcamentos-actions__icon--new">+</span>
        <span class="erp-orcamentos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editDevolucao" class="erp-orcamentos-actions__btn" data-erp-key="F3">
        <span class="erp-orcamentos-actions__icon">✎</span>
        <span class="erp-orcamentos-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelDevolucao" class="erp-orcamentos-actions__btn" data-erp-key="F4">
        <span class="erp-orcamentos-actions__icon erp-orcamentos-actions__icon--cancel">✕</span>
        <span class="erp-orcamentos-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-orcamentos-actions__btn" data-erp-key="F5">
        <span class="erp-orcamentos-actions__icon">↻</span>
        <span class="erp-orcamentos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir devolução')" class="erp-orcamentos-actions__btn" data-erp-key="F6">
        <span class="erp-orcamentos-actions__icon">🖨</span>
        <span class="erp-orcamentos-actions__label"><kbd>F6</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-orcamentos-actions__btn erp-orcamentos-actions__btn--close">
        <span class="erp-orcamentos-actions__icon erp-orcamentos-actions__icon--close">✕</span>
        <span class="erp-orcamentos-actions__label">Fechar</span>
    </button>
</div>
