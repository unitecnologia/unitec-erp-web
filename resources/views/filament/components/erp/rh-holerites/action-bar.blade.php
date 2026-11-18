<div class="erp-rh-holerites-actions">
    <button type="button" wire:click="createHolerite" class="erp-rh-holerites-actions__btn" data-erp-key="F2">
        <span class="erp-rh-holerites-actions__icon erp-rh-holerites-actions__icon--new">+</span>
        <span class="erp-rh-holerites-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editHolerite" class="erp-rh-holerites-actions__btn" data-erp-key="F3">
        <span class="erp-rh-holerites-actions__icon">✎</span>
        <span class="erp-rh-holerites-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteHolerite" class="erp-rh-holerites-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-holerites-actions__icon erp-rh-holerites-actions__icon--cancel">✕</span>
        <span class="erp-rh-holerites-actions__label"><kbd>Del</kbd> | Inativar</span>
    </button>
    <button type="button" wire:click="abrirHoleriteSelecionado" class="erp-rh-holerites-actions__btn" title="Abrir PDF do holerite">
        <span class="erp-rh-holerites-actions__icon">↗</span>
        <span class="erp-rh-holerites-actions__label">Abrir</span>
    </button>
    <button type="button" wire:click="openHoleriteImport" class="erp-rh-holerites-actions__btn">
        <span class="erp-rh-holerites-actions__icon">⇪</span>
        <span class="erp-rh-holerites-actions__label">Importar</span>
    </button>
    <button type="button" wire:click="imprimirHolerites" class="erp-rh-holerites-actions__btn" data-erp-key="F4">
        <span class="erp-rh-holerites-actions__icon">🖨</span>
        <span class="erp-rh-holerites-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-holerites-actions__btn" data-erp-key="F5">
        <span class="erp-rh-holerites-actions__icon">↻</span>
        <span class="erp-rh-holerites-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-holerites-actions__btn erp-rh-holerites-actions__btn--close">
        <span class="erp-rh-holerites-actions__icon erp-rh-holerites-actions__icon--close">✕</span>
        <span class="erp-rh-holerites-actions__label">Fechar</span>
    </button>
</div>
