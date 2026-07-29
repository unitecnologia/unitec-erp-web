<div class="erp-unidades-actions erp-recibos-actions">
    @if (erp_can('recibos.create'))
        <button type="button" wire:click="createRecibo" class="erp-unidades-actions__btn" data-erp-key="F2">
            <span class="erp-unidades-actions__icon erp-unidades-actions__icon--new">+</span>
            <span class="erp-unidades-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    @endif
    @if (erp_can('recibos.update'))
        <button type="button" wire:click="editRecibo" class="erp-unidades-actions__btn" data-erp-key="F3">
            <span class="erp-unidades-actions__icon">✎</span>
            <span class="erp-unidades-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    @endif
    <button type="button" wire:click="refreshTable" class="erp-unidades-actions__btn" data-erp-key="F5">
        <span class="erp-unidades-actions__icon">↻</span>
        <span class="erp-unidades-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    @if (erp_can('recibos.print'))
        <button type="button" wire:click="imprimirRecibo" class="erp-unidades-actions__btn" data-erp-key="F6">
            <span class="erp-unidades-actions__icon">🖨</span>
            <span class="erp-unidades-actions__label"><kbd>F6</kbd> | Imprimir</span>
        </button>
    @endif
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
