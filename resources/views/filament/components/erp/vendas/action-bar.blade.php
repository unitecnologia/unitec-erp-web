<div class="erp-vendas-actions">
    @if (erp_can('vendas.create'))
        <button type="button" wire:click="createVenda" class="erp-vendas-actions__btn" data-erp-key="F2">
            <span class="erp-vendas-actions__icon erp-vendas-actions__icon--new">+</span>
            <span class="erp-vendas-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    @endif
    @if (erp_can('vendas.update'))
        <button type="button" wire:click="editVenda" class="erp-vendas-actions__btn" data-erp-key="F3">
            <span class="erp-vendas-actions__icon">✎</span>
            <span class="erp-vendas-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    @endif
    @if (erp_can('vendas.cancel'))
        <button type="button" wire:click="cancelVenda" class="erp-vendas-actions__btn" data-erp-key="F4">
            <span class="erp-vendas-actions__icon erp-vendas-actions__icon--cancel">✕</span>
            <span class="erp-vendas-actions__label"><kbd>F4</kbd> | Cancelar</span>
        </button>
    @endif
    <button type="button" wire:click="refreshTable" class="erp-vendas-actions__btn" data-erp-key="F5">
        <span class="erp-vendas-actions__icon">↻</span>
        <span class="erp-vendas-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    @if (erp_can('vendas.print'))
        <button type="button" wire:click="printVendas" class="erp-vendas-actions__btn" data-erp-key="F6">
            <span class="erp-vendas-actions__icon">🖨</span>
            <span class="erp-vendas-actions__label"><kbd>F6</kbd> | Imprimir</span>
        </button>
    @endif
    <button type="button" wire:click="modulePending('E-mail')" class="erp-vendas-actions__btn" data-erp-key="F9">
        <span class="erp-vendas-actions__icon">✉</span>
        <span class="erp-vendas-actions__label"><kbd>F9</kbd> | Email</span>
    </button>
    <button type="button" wire:click="modulePending('WhatsApp')" class="erp-vendas-actions__btn" data-erp-key="F10">
        <span class="erp-vendas-actions__icon">📱</span>
        <span class="erp-vendas-actions__label"><kbd>F10</kbd> | Whats</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-vendas-actions__btn erp-vendas-actions__btn--close">
        <span class="erp-vendas-actions__icon erp-vendas-actions__icon--close">✕</span>
        <span class="erp-vendas-actions__label">Fechar</span>
    </button>
</div>
