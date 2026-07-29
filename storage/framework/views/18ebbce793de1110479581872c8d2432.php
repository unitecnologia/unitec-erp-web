<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->previewOverlayOpen && filled($this->previewOverlayUrl)): ?>
    <div
        class="erp-form-overlay erp-orc-preview-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizar orçamento"
        data-livewire-id="<?php echo e($this->getId()); ?>"
    >
        <div class="erp-form-overlay__backdrop" wire:click="closePreviewOverlay"></div>

        <div class="erp-form-overlay__panel">
            <iframe
                src="<?php echo e($this->previewOverlayUrl); ?>"
                class="erp-form-overlay__iframe"
                title="Visualizar orçamento"
                data-erp-orcamento-preview-iframe
            ></iframe>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/orcamentos/preview-overlay.blade.php ENDPATH**/ ?>