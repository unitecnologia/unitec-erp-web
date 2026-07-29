<div class="erp-produtos-actions">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('produtos.create')): ?>
        <button type="button" wire:click="createProduct" class="erp-produtos-actions__btn" data-erp-key="F2">
            <span class="erp-produtos-actions__icon">+</span>
            <span class="erp-produtos-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('produtos.update')): ?>
        <button type="button" wire:click="editProduct" class="erp-produtos-actions__btn" data-erp-key="F3">
            <span class="erp-produtos-actions__icon">✎</span>
            <span class="erp-produtos-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('produtos.print')): ?>
        <button type="button" wire:click="printProducts" class="erp-produtos-actions__btn" data-erp-key="F4">
            <span class="erp-produtos-actions__icon">🖨</span>
            <span class="erp-produtos-actions__label"><kbd>F4</kbd> | Imprimir</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="button" wire:click="refreshTable" class="erp-produtos-actions__btn" data-erp-key="F5">
        <span class="erp-produtos-actions__icon">↻</span>
        <span class="erp-produtos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('produtos.cardex')): ?>
        <button type="button" wire:click="openProductCardex" class="erp-produtos-actions__btn" data-erp-key="F7">
            <span class="erp-produtos-actions__icon">📋</span>
            <span class="erp-produtos-actions__label"><kbd>F7</kbd> | Histórico</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('produtos.duplicate')): ?>
        <button type="button" wire:click="duplicateProduct" class="erp-produtos-actions__btn" data-erp-key="F8">
            <span class="erp-produtos-actions__icon">⧉</span>
            <span class="erp-produtos-actions__label"><kbd>F8</kbd> | Duplicar</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="button" wire:click="closeScreen" class="erp-produtos-actions__btn erp-produtos-actions__btn--close">
        <span class="erp-produtos-actions__icon erp-produtos-actions__icon--close">✕</span>
        <span class="erp-produtos-actions__label">Fechar</span>
    </button>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/action-bar.blade.php ENDPATH**/ ?>