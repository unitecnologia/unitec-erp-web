<div class="erp-rh-treinamentos-actions">
    <button type="button" wire:click="createRealizacao" class="erp-rh-treinamentos-actions__btn" data-erp-key="F2">
        <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--new">+</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editParticipante" class="erp-rh-treinamentos-actions__btn" data-erp-key="F3">
        <span class="erp-rh-treinamentos-actions__icon">✎</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="cancelarParticipante" class="erp-rh-treinamentos-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--cancel">✕</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="openTreinamentoCatalogCreate" class="erp-rh-treinamentos-actions__btn">
        <span class="erp-rh-treinamentos-actions__icon">+</span>
        <span class="erp-rh-treinamentos-actions__label">Treinamento</span>
    </button>
    <button type="button" wire:click="historicoFuncionarioSelecionado" class="erp-rh-treinamentos-actions__btn">
        <span class="erp-rh-treinamentos-actions__icon">☰</span>
        <span class="erp-rh-treinamentos-actions__label">Histórico</span>
    </button>
    <label class="erp-rh-treinamentos-actions__btn erp-rh-treinamentos-actions__file" title="Anexar certificado do participante selecionado">
        <span class="erp-rh-treinamentos-actions__icon">↗</span>
        <span class="erp-rh-treinamentos-actions__label" wire:loading.remove wire:target="certificadoUpload">Anexar certificado</span>
        <span class="erp-rh-treinamentos-actions__label" wire:loading wire:target="certificadoUpload">…</span>
        <input type="file" wire:model="certificadoUpload" class="erp-rh-treinamentos-actions__file-input" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
    </label>
    <button type="button" wire:click="imprimirTreinamentos" class="erp-rh-treinamentos-actions__btn" data-erp-key="F4">
        <span class="erp-rh-treinamentos-actions__icon">🖨</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-treinamentos-actions__btn" data-erp-key="F5">
        <span class="erp-rh-treinamentos-actions__icon">↻</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-treinamentos-actions__btn erp-rh-treinamentos-actions__btn--close">
        <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--close">✕</span>
        <span class="erp-rh-treinamentos-actions__label">Fechar</span>
    </button>
</div>
@error('certificadoUpload')
    <div class="erp-rh-treinamentos-actions__error">{{ $message }}</div>
@enderror
