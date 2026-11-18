<div class="erp-rh-escalas-actions">
    <button type="button" wire:click="createVinculo" class="erp-rh-escalas-actions__btn" data-erp-key="F2">
        <span class="erp-rh-escalas-actions__icon erp-rh-escalas-actions__icon--new">+</span>
        <span class="erp-rh-escalas-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editVinculo" class="erp-rh-escalas-actions__btn" data-erp-key="F3">
        <span class="erp-rh-escalas-actions__icon">✎</span>
        <span class="erp-rh-escalas-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="encerrarVinculoSelecionado" class="erp-rh-escalas-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-escalas-actions__icon erp-rh-escalas-actions__icon--cancel">✕</span>
        <span class="erp-rh-escalas-actions__label"><kbd>Del</kbd> | Encerrar</span>
    </button>
    <button type="button" wire:click="openEscalaCatalogCreate" class="erp-rh-escalas-actions__btn">
        <span class="erp-rh-escalas-actions__icon">+</span>
        <span class="erp-rh-escalas-actions__label">Escala</span>
    </button>
    <button type="button" wire:click="openAjusteModal" class="erp-rh-escalas-actions__btn">
        <span class="erp-rh-escalas-actions__icon">⚙</span>
        <span class="erp-rh-escalas-actions__label">Ajuste dia</span>
    </button>
    <button type="button" wire:click="openCalendarioModal" class="erp-rh-escalas-actions__btn">
        <span class="erp-rh-escalas-actions__icon">📅</span>
        <span class="erp-rh-escalas-actions__label">Calendário</span>
    </button>
    <button type="button" wire:click="imprimirVinculos" class="erp-rh-escalas-actions__btn" data-erp-key="F4">
        <span class="erp-rh-escalas-actions__icon">🖨</span>
        <span class="erp-rh-escalas-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-escalas-actions__btn" data-erp-key="F5">
        <span class="erp-rh-escalas-actions__icon">↻</span>
        <span class="erp-rh-escalas-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-escalas-actions__btn erp-rh-escalas-actions__btn--close">
        <span class="erp-rh-escalas-actions__icon erp-rh-escalas-actions__icon--close">✕</span>
        <span class="erp-rh-escalas-actions__label">Fechar</span>
    </button>
</div>
