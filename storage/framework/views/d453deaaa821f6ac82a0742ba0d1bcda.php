<div class="erp-pessoas-actions">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('pessoas.create')): ?>
        <button type="button" wire:click="createPerson" class="erp-pessoas-actions__btn" data-erp-key="F2">
            <span class="erp-pessoas-actions__icon">+</span>
            <span class="erp-pessoas-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('pessoas.update')): ?>
        <button type="button" wire:click="editPerson" class="erp-pessoas-actions__btn" data-erp-key="F3">
            <span class="erp-pessoas-actions__icon">✎</span>
            <span class="erp-pessoas-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('pessoas.print')): ?>
        <button type="button" wire:click="printPeople" class="erp-pessoas-actions__btn" data-erp-key="F4">
            <span class="erp-pessoas-actions__icon">🖨</span>
            <span class="erp-pessoas-actions__label"><kbd>F4</kbd> | Imprimir</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="button" wire:click="refreshTable" class="erp-pessoas-actions__btn" data-erp-key="F5">
        <span class="erp-pessoas-actions__icon">↻</span>
        <span class="erp-pessoas-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-pessoas-actions__btn erp-pessoas-actions__btn--close">
        <span class="erp-pessoas-actions__icon erp-pessoas-actions__icon--close">✕</span>
        <span class="erp-pessoas-actions__label">Fechar</span>
    </button>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/action-bar.blade.php ENDPATH**/ ?>