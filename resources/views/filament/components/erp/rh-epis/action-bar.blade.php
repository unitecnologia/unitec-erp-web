<div class="erp-rh-epis-actions">
    <button type="button" wire:click="createEntrega" class="erp-rh-epis-actions__btn" data-erp-key="F2">
        <span class="erp-rh-epis-actions__icon erp-rh-epis-actions__icon--new">+</span>
        <span class="erp-rh-epis-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editEntrega" class="erp-rh-epis-actions__btn" data-erp-key="F3">
        <span class="erp-rh-epis-actions__icon">✎</span>
        <span class="erp-rh-epis-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelarEntrega" class="erp-rh-epis-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-epis-actions__icon erp-rh-epis-actions__icon--cancel">✕</span>
        <span class="erp-rh-epis-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="openDevolverModal" class="erp-rh-epis-actions__btn">
        <span class="erp-rh-epis-actions__icon">↩</span>
        <span class="erp-rh-epis-actions__label">Devolver</span>
    </button>
    <button type="button" wire:click="substituirEntrega" class="erp-rh-epis-actions__btn">
        <span class="erp-rh-epis-actions__icon">⇄</span>
        <span class="erp-rh-epis-actions__label">Substituir</span>
    </button>
    <button type="button" wire:click="openEpiCatalogCreate" class="erp-rh-epis-actions__btn">
        <span class="erp-rh-epis-actions__icon">+</span>
        <span class="erp-rh-epis-actions__label">Equipamento</span>
    </button>
    <button type="button" wire:click="historicoFuncionarioSelecionado" class="erp-rh-epis-actions__btn">
        <span class="erp-rh-epis-actions__icon">☰</span>
        <span class="erp-rh-epis-actions__label">Histórico</span>
    </button>
    <button type="button" wire:click="imprimirComprovanteSelecionado" class="erp-rh-epis-actions__btn">
        <span class="erp-rh-epis-actions__icon">📋</span>
        <span class="erp-rh-epis-actions__label">Comprovante</span>
    </button>
    <label class="erp-rh-epis-actions__btn erp-rh-epis-actions__file" title="Anexar comprovante assinado da entrega selecionada">
        <span class="erp-rh-epis-actions__icon">↗</span>
        <span class="erp-rh-epis-actions__label" wire:loading.remove wire:target="comprovanteUpload">Anexar</span>
        <span class="erp-rh-epis-actions__label" wire:loading wire:target="comprovanteUpload">…</span>
        <input type="file" wire:model="comprovanteUpload" class="erp-rh-epis-actions__file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
    </label>
    <button type="button" wire:click="imprimirEntregas" class="erp-rh-epis-actions__btn" data-erp-key="F4">
        <span class="erp-rh-epis-actions__icon">🖨</span>
        <span class="erp-rh-epis-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-epis-actions__btn" data-erp-key="F5">
        <span class="erp-rh-epis-actions__icon">↻</span>
        <span class="erp-rh-epis-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-epis-actions__btn erp-rh-epis-actions__btn--close">
        <span class="erp-rh-epis-actions__icon erp-rh-epis-actions__icon--close">✕</span>
        <span class="erp-rh-epis-actions__label">Fechar</span>
    </button>
</div>
@error('comprovanteUpload')
    <div class="erp-rh-epis-actions__error">{{ $message }}</div>
@enderror
