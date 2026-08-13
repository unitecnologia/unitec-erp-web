<div class="erp-unidades-actions">
    @if (erp_can('cfops.create'))
        <button type="button" wire:click="createCfop" class="erp-unidades-actions__btn" data-erp-key="F2">
            <span class="erp-unidades-actions__icon erp-unidades-actions__icon--new">+</span>
            <span class="erp-unidades-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    @endif
    @if (erp_can('cfops.update'))
        <button type="button" wire:click="editCfop" class="erp-unidades-actions__btn" data-erp-key="F3">
            <span class="erp-unidades-actions__icon">✎</span>
            <span class="erp-unidades-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    @endif
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
