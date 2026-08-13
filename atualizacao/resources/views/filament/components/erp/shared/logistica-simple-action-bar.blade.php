@php
    $pageClass = $pageClass ?? 'erp-tomadores-servico';
    $actionsClass = $pageClass . '-actions';
    $createMethod = $createMethod ?? 'createErpCn';
    $editMethod = $editMethod ?? 'editErpCn';
    $deleteMethod = $deleteMethod ?? 'deleteErpCn';
    $showDelete = $showDelete ?? true;
@endphp

<div class="{{ $actionsClass }}">
    <button type="button" wire:click="{{ $createMethod }}" class="{{ $actionsClass }}__btn" data-erp-key="F2">
        <span class="{{ $actionsClass }}__icon {{ $actionsClass }}__icon--new">+</span>
        <span class="{{ $actionsClass }}__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="{{ $editMethod }}" class="{{ $actionsClass }}__btn" data-erp-key="F3">
        <span class="{{ $actionsClass }}__icon">✎</span>
        <span class="{{ $actionsClass }}__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    @if ($showDelete)
        <button type="button" wire:click="{{ $deleteMethod }}" class="{{ $actionsClass }}__btn" data-erp-key="Delete">
            <span class="{{ $actionsClass }}__icon {{ $actionsClass }}__icon--cancel">✕</span>
            <span class="{{ $actionsClass }}__label"><kbd>Del</kbd> | Excluir</span>
        </button>
    @endif
    <button type="button" wire:click="modulePending('Imprimir')" class="{{ $actionsClass }}__btn" data-erp-key="F4">
        <span class="{{ $actionsClass }}__icon">🖨</span>
        <span class="{{ $actionsClass }}__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="{{ $actionsClass }}__btn" data-erp-key="F5">
        <span class="{{ $actionsClass }}__icon">↻</span>
        <span class="{{ $actionsClass }}__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="{{ $actionsClass }}__btn {{ $actionsClass }}__btn--close">
        <span class="{{ $actionsClass }}__icon {{ $actionsClass }}__icon--close">✕</span>
        <span class="{{ $actionsClass }}__label">Fechar</span>
    </button>
</div>
