<div class="erp-unidades-actions">
    <button type="button" wire:click="gerarRemessa" class="erp-unidades-actions__btn" data-erp-key="F2">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--new">+</span>
        <span class="erp-unidades-actions__label"><kbd>F2</kbd> | Gerar</span>
    </button>
    <button type="button" wire:click="verTitulos" class="erp-unidades-actions__btn" data-erp-key="F3">
        <span class="erp-unidades-actions__icon">☰</span>
        <span class="erp-unidades-actions__label"><kbd>F3</kbd> | Títulos</span>
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
