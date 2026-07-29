<div class="erp-pagar-actions">
    @if ($this->viewTab === 'desdobramentos')
        <button type="button" wire:click="pedirEstornoDesdobramento" class="erp-pagar-actions__btn erp-pagar-actions__btn--estorno" data-erp-key="F8">
            <span class="erp-pagar-actions__icon erp-pagar-actions__icon--estorno">↩</span>
            <span class="erp-pagar-actions__label"><kbd>F8</kbd> | Estornar</span>
        </button>
        <button type="button" wire:click="voltarParaTitulos" class="erp-pagar-actions__btn">
            <span class="erp-pagar-actions__icon">←</span>
            <span class="erp-pagar-actions__label">Voltar aos Títulos</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-pagar-actions__btn erp-pagar-actions__btn--close">
            <span class="erp-pagar-actions__icon erp-pagar-actions__icon--close">✕</span>
            <span class="erp-pagar-actions__label">Fechar</span>
        </button>
    @else
        <button type="button" wire:click="createConta" class="erp-pagar-actions__btn" data-erp-key="F2">
            <span class="erp-pagar-actions__icon erp-pagar-actions__icon--new">+</span>
            <span class="erp-pagar-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
        <button type="button" wire:click="editConta" class="erp-pagar-actions__btn" data-erp-key="F3">
            <span class="erp-pagar-actions__icon">✎</span>
            <span class="erp-pagar-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
        <button type="button" class="erp-pagar-actions__btn" disabled title="Em breve">
            <span class="erp-pagar-actions__icon">🖨</span>
            <span class="erp-pagar-actions__label"><kbd>F4</kbd> | Imprimir</span>
        </button>
        <button type="button" wire:click="refreshTable" class="erp-pagar-actions__btn" data-erp-key="F5">
            <span class="erp-pagar-actions__icon">↻</span>
            <span class="erp-pagar-actions__label"><kbd>F5</kbd> | Atualizar</span>
        </button>
        <button type="button" wire:click="baixarConta" class="erp-pagar-actions__btn erp-pagar-actions__btn--baixar" data-erp-key="F7">
            <span class="erp-pagar-actions__icon erp-pagar-actions__icon--baixar">↓</span>
            <span class="erp-pagar-actions__label"><kbd>F7</kbd> | Baixar</span>
        </button>
        <button type="button" wire:click="verHistoricoPagamentos" class="erp-pagar-actions__btn" data-erp-key="F8">
            <span class="erp-pagar-actions__icon">☰</span>
            <span class="erp-pagar-actions__label"><kbd>F8</kbd> | Histórico</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-pagar-actions__btn erp-pagar-actions__btn--close">
            <span class="erp-pagar-actions__icon erp-pagar-actions__icon--close">✕</span>
            <span class="erp-pagar-actions__label">Fechar</span>
        </button>
    @endif
</div>
