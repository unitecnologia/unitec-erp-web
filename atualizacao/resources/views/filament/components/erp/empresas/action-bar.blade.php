<div class="erp-empresas-actions">
    <button type="button" wire:click="createEmpresa" class="erp-empresas-actions__btn" data-erp-key="F2">
        <span class="erp-empresas-actions__icon">+</span>
        <span class="erp-empresas-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editEmpresa" class="erp-empresas-actions__btn" data-erp-key="F3">
        <span class="erp-empresas-actions__icon">✎</span>
        <span class="erp-empresas-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="focusEmpresaSearch" class="erp-empresas-actions__btn" data-erp-key="F5">
        <span class="erp-empresas-actions__icon">↻</span>
        <span class="erp-empresas-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-empresas-actions__btn erp-empresas-actions__btn--close">
        <span class="erp-empresas-actions__icon erp-empresas-actions__icon--close">✕</span>
        <span class="erp-empresas-actions__label">Fechar</span>
    </button>
</div>
