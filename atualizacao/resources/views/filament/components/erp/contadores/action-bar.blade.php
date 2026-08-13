<div class="erp-contadores-actions">
    <button type="button" wire:click="createContador" class="erp-contadores-actions__btn" data-erp-key="F2">
        <span class="erp-contadores-actions__icon erp-contadores-actions__icon--new">+</span>
        <span class="erp-contadores-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editContador" class="erp-contadores-actions__btn" data-erp-key="F3">
        <span class="erp-contadores-actions__icon">✎</span>
        <span class="erp-contadores-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteContador" class="erp-contadores-actions__btn" data-erp-key="Delete">
        <span class="erp-contadores-actions__icon erp-contadores-actions__icon--cancel">✕</span>
        <span class="erp-contadores-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-contadores-actions__btn" data-erp-key="F4">
        <span class="erp-contadores-actions__icon">🖨</span>
        <span class="erp-contadores-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-contadores-actions__btn" data-erp-key="F5">
        <span class="erp-contadores-actions__icon">↻</span>
        <span class="erp-contadores-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-contadores-actions__btn erp-contadores-actions__btn--close">
        <span class="erp-contadores-actions__icon erp-contadores-actions__icon--close">✕</span>
        <span class="erp-contadores-actions__label">Fechar</span>
    </button>
</div>
