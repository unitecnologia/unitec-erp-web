<div class="erp-transportadoras-actions">
    <button type="button" wire:click="createTransportadora" class="erp-transportadoras-actions__btn" data-erp-key="F2">
        <span class="erp-transportadoras-actions__icon erp-transportadoras-actions__icon--new">+</span>
        <span class="erp-transportadoras-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editTransportadora" class="erp-transportadoras-actions__btn" data-erp-key="F3">
        <span class="erp-transportadoras-actions__icon">✎</span>
        <span class="erp-transportadoras-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-transportadoras-actions__btn" data-erp-key="F5">
        <span class="erp-transportadoras-actions__icon">↻</span>
        <span class="erp-transportadoras-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-transportadoras-actions__btn erp-transportadoras-actions__btn--close">
        <span class="erp-transportadoras-actions__icon erp-transportadoras-actions__icon--close">✕</span>
        <span class="erp-transportadoras-actions__label">Fechar</span>
    </button>
</div>
