<div class="erp-aniversariantes-actions">
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-aniversariantes-actions__btn" data-erp-key="F4">
        <span class="erp-aniversariantes-actions__icon">🖨</span>
        <span class="erp-aniversariantes-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-aniversariantes-actions__btn" data-erp-key="F5">
        <span class="erp-aniversariantes-actions__icon">↻</span>
        <span class="erp-aniversariantes-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-aniversariantes-actions__btn erp-aniversariantes-actions__btn--close">
        <span class="erp-aniversariantes-actions__icon erp-aniversariantes-actions__icon--close">✕</span>
        <span class="erp-aniversariantes-actions__label">Fechar</span>
    </button>
</div>
