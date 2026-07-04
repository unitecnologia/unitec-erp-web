<div class="erp-entregadores-actions">
    <button type="button" wire:click="autorizarAparelho" class="erp-entregadores-actions__btn" data-erp-key="F2">
        <span class="erp-entregadores-actions__icon erp-entregadores-actions__icon--ok">✔</span>
        <span class="erp-entregadores-actions__label"><kbd>F2</kbd> | Autorizar</span>
    </button>
    <button type="button" wire:click="revogarAparelho" class="erp-entregadores-actions__btn" data-erp-key="F4">
        <span class="erp-entregadores-actions__icon erp-entregadores-actions__icon--cancel">⛔</span>
        <span class="erp-entregadores-actions__label"><kbd>F4</kbd> | Revogar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-entregadores-actions__btn" data-erp-key="F5">
        <span class="erp-entregadores-actions__icon">↻</span>
        <span class="erp-entregadores-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-entregadores-actions__btn erp-entregadores-actions__btn--close">
        <span class="erp-entregadores-actions__icon erp-entregadores-actions__icon--close">✕</span>
        <span class="erp-entregadores-actions__label">Fechar</span>
    </button>
</div>
