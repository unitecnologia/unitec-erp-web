<div class="erp-compras-actions">
    <button type="button" wire:click="createCompra" class="erp-compras-actions__btn" data-erp-key="F2">
        <span class="erp-compras-actions__icon erp-compras-actions__icon--new">+</span>
        <span class="erp-compras-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editCompra" class="erp-compras-actions__btn" data-erp-key="F3">
        <span class="erp-compras-actions__icon">✎</span>
        <span class="erp-compras-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelCompra" class="erp-compras-actions__btn" data-erp-key="F4">
        <span class="erp-compras-actions__icon erp-compras-actions__icon--cancel">✕</span>
        <span class="erp-compras-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-compras-actions__btn" data-erp-key="F5">
        <span class="erp-compras-actions__icon">↻</span>
        <span class="erp-compras-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="modulePending('Ler XML')" class="erp-compras-actions__btn" data-erp-key="F6">
        <span class="erp-compras-actions__icon">📄</span>
        <span class="erp-compras-actions__label"><kbd>F6</kbd> | Ler XML</span>
    </button>
    <button type="button" wire:click="modulePending('Fechar Mês')" class="erp-compras-actions__btn" data-erp-key="F9">
        <span class="erp-compras-actions__icon">📅</span>
        <span class="erp-compras-actions__label"><kbd>F9</kbd> | Fechar Mês</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-compras-actions__btn erp-compras-actions__btn--close">
        <span class="erp-compras-actions__icon erp-compras-actions__icon--close">✕</span>
        <span class="erp-compras-actions__label">Fechar</span>
    </button>
</div>
