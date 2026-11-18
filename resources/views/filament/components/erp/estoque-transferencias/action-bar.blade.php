<div class="erp-ajustes-estoque-actions">
    <button type="button" wire:click="createTransferencia" class="erp-ajustes-estoque-actions__btn" data-erp-key="F2">
        <span class="erp-ajustes-estoque-actions__icon erp-ajustes-estoque-actions__icon--new">+</span>
        <span class="erp-ajustes-estoque-actions__label"><kbd>F2</kbd> | Nova</span>
    </button>
    <button type="button" wire:click="viewTransferencia" class="erp-ajustes-estoque-actions__btn" data-erp-key="F3">
        <span class="erp-ajustes-estoque-actions__icon">👁</span>
        <span class="erp-ajustes-estoque-actions__label"><kbd>F3</kbd> | Abrir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-ajustes-estoque-actions__btn" data-erp-key="F5">
        <span class="erp-ajustes-estoque-actions__icon">↻</span>
        <span class="erp-ajustes-estoque-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-ajustes-estoque-actions__btn erp-ajustes-estoque-actions__btn--close">
        <span class="erp-ajustes-estoque-actions__icon erp-ajustes-estoque-actions__icon--close">✕</span>
        <span class="erp-ajustes-estoque-actions__label">Fechar</span>
    </button>
</div>
