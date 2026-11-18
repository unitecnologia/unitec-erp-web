<div class="erp-rh-ferias-actions">
    <button type="button" wire:click="createFeriasGozo" class="erp-rh-ferias-actions__btn" data-erp-key="F2">
        <span class="erp-rh-ferias-actions__icon erp-rh-ferias-actions__icon--new">+</span>
        <span class="erp-rh-ferias-actions__label"><kbd>F2</kbd> | Programar</span>
    </button>
    <button type="button" wire:click="editFeriasGozo" class="erp-rh-ferias-actions__btn" data-erp-key="F3">
        <span class="erp-rh-ferias-actions__icon">✎</span>
        <span class="erp-rh-ferias-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="openFeriasPeriodoModal" class="erp-rh-ferias-actions__btn">
        <span class="erp-rh-ferias-actions__icon">📅</span>
        <span class="erp-rh-ferias-actions__label">Gerar período</span>
    </button>
    <button type="button" wire:click="marcarFeriasEmAndamento" class="erp-rh-ferias-actions__btn">
        <span class="erp-rh-ferias-actions__icon">▶</span>
        <span class="erp-rh-ferias-actions__label">Em andamento</span>
    </button>
    <button type="button" wire:click="concluirFeriasGozo" class="erp-rh-ferias-actions__btn">
        <span class="erp-rh-ferias-actions__icon">✓</span>
        <span class="erp-rh-ferias-actions__label">Concluir</span>
    </button>
    <button type="button" wire:click="cancelarFeriasGozo" class="erp-rh-ferias-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-ferias-actions__icon erp-rh-ferias-actions__icon--cancel">✕</span>
        <span class="erp-rh-ferias-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="openAjusteSaldoModal" class="erp-rh-ferias-actions__btn">
        <span class="erp-rh-ferias-actions__icon">⚙</span>
        <span class="erp-rh-ferias-actions__label">Ajuste saldo</span>
    </button>
    <button type="button" wire:click="abrirAvisoFeriasSelecionado" class="erp-rh-ferias-actions__btn">
        <span class="erp-rh-ferias-actions__icon">↗</span>
        <span class="erp-rh-ferias-actions__label">Abrir aviso</span>
    </button>
    <button type="button" wire:click="imprimirFerias" class="erp-rh-ferias-actions__btn" data-erp-key="F4">
        <span class="erp-rh-ferias-actions__icon">🖨</span>
        <span class="erp-rh-ferias-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-ferias-actions__btn" data-erp-key="F5">
        <span class="erp-rh-ferias-actions__icon">↻</span>
        <span class="erp-rh-ferias-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-ferias-actions__btn erp-rh-ferias-actions__btn--close">
        <span class="erp-rh-ferias-actions__icon erp-rh-ferias-actions__icon--close">✕</span>
        <span class="erp-rh-ferias-actions__label">Fechar</span>
    </button>
</div>
