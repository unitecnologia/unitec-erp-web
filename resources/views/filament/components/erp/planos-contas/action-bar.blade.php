<div class="erp-unidades-actions">
    <button type="button" wire:click="createPlanoConta" class="erp-unidades-actions__btn" data-erp-key="F2">
        <span class="erp-unidades-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editPlanoConta" class="erp-unidades-actions__btn" data-erp-key="F3">
        <span class="erp-unidades-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir plano de contas')" class="erp-unidades-actions__btn" data-erp-key="F7">
        <span class="erp-unidades-actions__label"><kbd>F7</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
