<div class="erp-rh-ocorrencias-actions">
    <button type="button" wire:click="createOcorrencia" class="erp-rh-ocorrencias-actions__btn" data-erp-key="F2">
        <span class="erp-rh-ocorrencias-actions__icon erp-rh-ocorrencias-actions__icon--new">+</span>
        <span class="erp-rh-ocorrencias-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editOcorrencia" class="erp-rh-ocorrencias-actions__btn" data-erp-key="F3">
        <span class="erp-rh-ocorrencias-actions__icon">✎</span>
        <span class="erp-rh-ocorrencias-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelarOcorrencia" class="erp-rh-ocorrencias-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-ocorrencias-actions__icon erp-rh-ocorrencias-actions__icon--cancel">✕</span>
        <span class="erp-rh-ocorrencias-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="abrirAnexoSelecionado" class="erp-rh-ocorrencias-actions__btn" title="Abrir anexo da ocorrência">
        <span class="erp-rh-ocorrencias-actions__icon">↗</span>
        <span class="erp-rh-ocorrencias-actions__label">Abrir anexo</span>
    </button>
    <button type="button" wire:click="imprimirOcorrencias" class="erp-rh-ocorrencias-actions__btn" data-erp-key="F4">
        <span class="erp-rh-ocorrencias-actions__icon">🖨</span>
        <span class="erp-rh-ocorrencias-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="imprimirRegistroSelecionado" class="erp-rh-ocorrencias-actions__btn" title="Imprimir registro selecionado">
        <span class="erp-rh-ocorrencias-actions__icon">🖨</span>
        <span class="erp-rh-ocorrencias-actions__label">Imprimir registro</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-ocorrencias-actions__btn" data-erp-key="F5">
        <span class="erp-rh-ocorrencias-actions__icon">↻</span>
        <span class="erp-rh-ocorrencias-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-ocorrencias-actions__btn erp-rh-ocorrencias-actions__btn--close">
        <span class="erp-rh-ocorrencias-actions__icon erp-rh-ocorrencias-actions__icon--close">✕</span>
        <span class="erp-rh-ocorrencias-actions__label">Fechar</span>
    </button>
</div>
