<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->duplicateConfirmOpen): ?>
    <?php
        $duplicate = $this->duplicateConfirmViewState;
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($duplicate !== []): ?>
        <div
            class="erp-lookup-modal erp-duplicate-modal"
            wire:keydown.escape="handleDuplicateEscape"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="cancelDuplicateConfirmModal"></div>

            <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-duplicate-title">
                <div class="erp-lookup-modal__titlebar">
                    <span id="erp-duplicate-title">Produto já cadastrado</span>
                    <button type="button" class="erp-lookup-modal__close" wire:click="cancelDuplicateConfirmModal" title="Fechar">✕</button>
                </div>

                <div class="erp-lookup-modal__body erp-duplicate-modal__body">
                    <p class="erp-duplicate-modal__reason"><?php echo e($duplicate['matchLabel']); ?></p>

                    <dl class="erp-duplicate-modal__details">
                        <div class="erp-duplicate-modal__detail">
                            <dt>Código</dt>
                            <dd><?php echo e($duplicate['codigo']); ?></dd>
                        </div>
                        <div class="erp-duplicate-modal__detail">
                            <dt>Descrição</dt>
                            <dd><?php echo e($duplicate['descricao']); ?></dd>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($duplicate['codigo_barras'])): ?>
                            <div class="erp-duplicate-modal__detail">
                                <dt>Código de barras</dt>
                                <dd><?php echo e($duplicate['codigo_barras']); ?></dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </dl>

                    <p class="erp-duplicate-modal__question">Deseja editar o produto existente?</p>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="confirmEditExistingProduct" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label">Sim, editar</span>
                    </button>
                    <button type="button" wire:click="cancelDuplicateConfirmModal" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Não</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/duplicate-confirm-modal.blade.php ENDPATH**/ ?>