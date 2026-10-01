<div class="erp-nfe-actions">
    <button type="button" wire:click="createNfse" class="erp-nfe-actions__btn" data-erp-key="F2">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">+</span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editNfse" class="erp-nfe-actions__btn" data-erp-key="F3">
        <span class="erp-nfe-actions__icon">✎</span>
        <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelarNfse" class="erp-nfe-actions__btn" data-erp-key="F4">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">✕</span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="imprimirNfse" class="erp-nfe-actions__btn" data-erp-key="F7">
        <span class="erp-nfe-actions__icon">🖨</span>
        <span class="erp-nfe-actions__label"><kbd>F7</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="enviarNfse" class="erp-nfe-actions__btn" data-erp-key="F9">
        <span class="erp-nfe-actions__icon">✉</span>
        <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Enviar</span>
    </button>
    <button type="button" wire:click="printRelatorioNfse" class="erp-nfe-actions__btn" data-erp-key="F10">
        <span class="erp-nfe-actions__icon">📊</span>
        <span class="erp-nfe-actions__label"><kbd>F10</kbd> | Relatório</span>
    </button>
    <button type="button" wire:click="gerarPdfNfse" class="erp-nfe-actions__btn" data-erp-key="F12">
        <span class="erp-nfe-actions__icon">📄</span>
        <span class="erp-nfe-actions__label"><kbd>F12</kbd> | Gerar PDF</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">↻</span>
        <span class="erp-nfe-actions__label">Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">✕</span>
        <span class="erp-nfe-actions__label">Fechar</span>
    </button>
</div>
