<div class="erp-unidades-actions">
    <button type="button" wire:click="autorizarAparelho" class="erp-unidades-actions__btn" data-erp-key="F2">
        <span class="erp-unidades-actions__icon">✔</span>
        <span class="erp-unidades-actions__label"><kbd>F2</kbd> | Autorizar</span>
    </button>
    <button type="button" wire:click="revogarAparelho" class="erp-unidades-actions__btn" data-erp-key="F4">
        <span class="erp-unidades-actions__icon">⛔</span>
        <span class="erp-unidades-actions__label"><kbd>F4</kbd> | Revogar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-unidades-actions__btn" data-erp-key="F5">
        <span class="erp-unidades-actions__icon">↻</span>
        <span class="erp-unidades-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
