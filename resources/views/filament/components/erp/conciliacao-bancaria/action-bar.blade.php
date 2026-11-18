{{-- Action bar no padrão RH Treinamentos (C3: Conciliar / Desfazer + Fechar). --}}
<div class="erp-rh-treinamentos-actions erp-conciliacao-actions">
    <button type="button" wire:click="openConciliarModal" class="erp-rh-treinamentos-actions__btn" data-erp-key="F3">
        <span class="erp-rh-treinamentos-actions__icon">↔</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>F3</kbd> | Conciliar</span>
    </button>
    <button type="button" wire:click="openDesfazerConfirm" class="erp-rh-treinamentos-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--cancel">↺</span>
        <span class="erp-rh-treinamentos-actions__label"><kbd>Del</kbd> | Desfazer</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-treinamentos-actions__btn erp-rh-treinamentos-actions__btn--close">
        <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--close">✕</span>
        <span class="erp-rh-treinamentos-actions__label">Fechar</span>
    </button>
</div>

@include('filament.components.erp.conciliacao-bancaria.conciliar-modal')
@include('filament.components.erp.conciliacao-bancaria.conciliar-fraca-confirm-modal')
@include('filament.components.erp.conciliacao-bancaria.desfazer-confirm-modal')
