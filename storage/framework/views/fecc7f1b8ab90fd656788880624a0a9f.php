<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->overlayProductOpen): ?>
    <?php echo $__env->make('filament.components.erp.form-overlay', [
        'title' => 'Cadastro de Produtos',
        'iframeUrl' => $this->productOverlayUrl,
        'closeAction' => 'closeProductOverlay',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/product-overlay.blade.php ENDPATH**/ ?>