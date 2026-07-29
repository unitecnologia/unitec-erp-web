<?php
    use App\Support\Erp\ErpMenu;

    $shortcuts = ErpMenu::shortcuts();
?>

<div class="erp-shortcut-bar" aria-label="Atalhos rápidos">
    <div class="erp-shortcut-bar__scroll">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $shortcuts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shortcut): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($shortcut['logout'] ?? false): ?>
                <form method="POST" action="<?php echo e(filament()->getLogoutUrl()); ?>" class="erp-shortcut-bar__form">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="erp-shortcut erp-shortcut--<?php echo e($shortcut['color']); ?>" title="Alt+S">
                        <?php echo $__env->make('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <span class="erp-shortcut__label"><?php echo e($shortcut['label']); ?></span>
                    </button>
                </form>
            <?php elseif(filled($shortcut['url'] ?? null) && ! ($shortcut['disabled'] ?? false)): ?>
                <a href="<?php echo e($shortcut['url']); ?>" wire:navigate="false" class="erp-shortcut erp-shortcut--<?php echo e($shortcut['color']); ?>">
                    <?php echo $__env->make('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <span class="erp-shortcut__label"><?php echo e($shortcut['label']); ?></span>
                </a>
            <?php else: ?>
                <button
                    type="button"
                    class="erp-shortcut erp-shortcut--<?php echo e($shortcut['color']); ?> <?php if($shortcut['disabled'] ?? false): ?> erp-shortcut--disabled <?php endif; ?>"
                    <?php if($shortcut['disabled'] ?? false): ?> disabled <?php else: ?> data-erp-module="<?php echo e($shortcut['label']); ?>" <?php endif; ?>
                >
                    <?php echo $__env->make('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <span class="erp-shortcut__label"><?php echo e($shortcut['label']); ?></span>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/shortcut-bar.blade.php ENDPATH**/ ?>