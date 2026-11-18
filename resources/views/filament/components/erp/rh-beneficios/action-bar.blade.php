<div class="erp-rh-beneficios-actions">
    <button type="button" wire:click="createVinculo" class="erp-rh-beneficios-actions__btn" data-erp-key="F2">
        <span class="erp-rh-beneficios-actions__icon erp-rh-beneficios-actions__icon--new">+</span>
        <span class="erp-rh-beneficios-actions__label"><kbd>F2</kbd> | Novo vínculo</span>
    </button>
    <button type="button" wire:click="editVinculo" class="erp-rh-beneficios-actions__btn" data-erp-key="F3">
        <span class="erp-rh-beneficios-actions__icon">✎</span>
        <span class="erp-rh-beneficios-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelarVinculo" class="erp-rh-beneficios-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-beneficios-actions__icon erp-rh-beneficios-actions__icon--cancel">✕</span>
        <span class="erp-rh-beneficios-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="openEncerrarModal" class="erp-rh-beneficios-actions__btn">
        <span class="erp-rh-beneficios-actions__icon">⏹</span>
        <span class="erp-rh-beneficios-actions__label">Encerrar</span>
    </button>
    <button type="button" wire:click="openBeneficioCatalog" class="erp-rh-beneficios-actions__btn">
        <span class="erp-rh-beneficios-actions__icon">☰</span>
        <span class="erp-rh-beneficios-actions__label">Catálogo</span>
    </button>
    <button type="button" wire:click="imprimirBeneficios" class="erp-rh-beneficios-actions__btn" data-erp-key="F4">
        <span class="erp-rh-beneficios-actions__icon">🖨</span>
        <span class="erp-rh-beneficios-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-beneficios-actions__btn" data-erp-key="F5">
        <span class="erp-rh-beneficios-actions__icon">↻</span>
        <span class="erp-rh-beneficios-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-beneficios-actions__btn erp-rh-beneficios-actions__btn--close">
        <span class="erp-rh-beneficios-actions__icon erp-rh-beneficios-actions__icon--close">✕</span>
        <span class="erp-rh-beneficios-actions__label">Fechar</span>
    </button>
</div>
@error('comprovanteUpload')
    <div class="erp-rh-beneficios-actions__error">{{ $message }}</div>
@enderror
