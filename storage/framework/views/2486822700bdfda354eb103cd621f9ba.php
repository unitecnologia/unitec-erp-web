<div class="erp-unidades-actions">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(erp_can('cfops.update')): ?>
        <button type="button" wire:click="editCfop" class="erp-unidades-actions__btn" data-erp-key="F3">
            <span class="erp-unidades-actions__icon">✎</span>
            <span class="erp-unidades-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/cfops/action-bar.blade.php ENDPATH**/ ?>