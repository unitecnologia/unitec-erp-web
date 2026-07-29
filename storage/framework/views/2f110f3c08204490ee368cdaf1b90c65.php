<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->ncmConfirmOpen): ?>
    <div
        class="erp-lookup-modal erp-ncm-confirm-modal"
        wire:keydown.escape.window="cancelCadastrarNcm"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelCadastrarNcm"></div>

        <div
            class="erp-lookup-modal__window erp-ncm-confirm-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-ncm-confirm-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-ncm-confirm-title">NCM não cadastrado</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelCadastrarNcm" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-ncm-confirm-modal__body">
                <p class="erp-ncm-confirm-modal__text">
                    O NCM <strong><?php echo e($this->ncmConfirmCodigo); ?></strong> não existe na tabela de NCMs.
                </p>
                <p class="erp-ncm-confirm-modal__question">
                    Deseja cadastrar este NCM agora?
                </p>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="confirmCadastrarNcm" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Sim — cadastrar</span>
                </button>
                <button type="button" wire:click="cancelCadastrarNcm" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Não</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/ncm-confirm-modal.blade.php ENDPATH**/ ?>