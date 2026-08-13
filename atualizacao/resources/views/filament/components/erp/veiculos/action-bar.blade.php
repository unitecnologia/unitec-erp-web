<div class="erp-veiculos-actions">
    <button type="button" wire:click="createVeiculo" class="erp-veiculos-actions__btn" data-erp-key="F2">
        <span class="erp-veiculos-actions__icon erp-veiculos-actions__icon--new">+</span>
        <span class="erp-veiculos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editVeiculo" class="erp-veiculos-actions__btn" data-erp-key="F3">
        <span class="erp-veiculos-actions__icon">✎</span>
        <span class="erp-veiculos-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteVeiculo" class="erp-veiculos-actions__btn" data-erp-key="Delete">
        <span class="erp-veiculos-actions__icon erp-veiculos-actions__icon--cancel">✕</span>
        <span class="erp-veiculos-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-veiculos-actions__btn" data-erp-key="F4">
        <span class="erp-veiculos-actions__icon">🖨</span>
        <span class="erp-veiculos-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-veiculos-actions__btn" data-erp-key="F5">
        <span class="erp-veiculos-actions__icon">↻</span>
        <span class="erp-veiculos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-veiculos-actions__btn erp-veiculos-actions__btn--close">
        <span class="erp-veiculos-actions__icon erp-veiculos-actions__icon--close">✕</span>
        <span class="erp-veiculos-actions__label">Fechar</span>
    </button>
</div>
