<div class="erp-rh-documentos-actions">
    <button type="button" wire:click="createDestino" class="erp-rh-documentos-actions__btn" data-erp-key="F2">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--new">+</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editDestino" class="erp-rh-documentos-actions__btn" data-erp-key="F3">
        <span class="erp-rh-documentos-actions__icon">✎</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteDestino" class="erp-rh-documentos-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--cancel">✕</span>
        <span class="erp-rh-documentos-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-documentos-actions__btn" data-erp-key="F5">
        <span class="erp-rh-documentos-actions__icon">↻</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-documentos-actions__btn erp-rh-documentos-actions__btn--close">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--close">✕</span>
        <span class="erp-rh-documentos-actions__label">Fechar</span>
    </button>
</div>
