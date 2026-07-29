<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($item['type'] ?? null) === 'separator'): ?>
        <div class="erp-menu-bar__separator"></div>
    <?php elseif(! empty($item['items'])): ?>
        <div class="erp-menu-bar__submenu">
            <span class="erp-menu-bar__link erp-menu-bar__link--submenu" role="button" tabindex="0"><?php echo e($item['label']); ?></span>
            <div class="erp-menu-bar__submenu-panel" role="menu">
                <?php echo $__env->make('filament.components.erp.menu-bar-items', ['items' => $item['items']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>
    <?php elseif(filled($item['url'] ?? null)): ?>
        <a href="<?php echo e($item['url']); ?>" wire:navigate="false" class="erp-menu-bar__link"><?php echo e($item['label']); ?></a>
    <?php elseif(filled($item['action'] ?? null)): ?>
        <button type="button" class="erp-menu-bar__link" data-erp-action="<?php echo e($item['action']); ?>">
            <?php echo e($item['label']); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($item['shortcut'] ?? null)): ?>
                <kbd class="erp-kbd"><?php echo e($item['shortcut']); ?></kbd>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </button>
    <?php elseif(! empty($item['pending'])): ?>
        <span
            class="erp-menu-bar__link erp-menu-bar__link--pending"
            title="Em breve"
            aria-disabled="true"
        >
            <span><?php echo e($item['label']); ?></span>
            <span class="erp-menu-bar__badge">Em breve</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($item['shortcut'] ?? null)): ?>
                <kbd class="erp-kbd"><?php echo e($item['shortcut']); ?></kbd>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </span>
    <?php else: ?>
        <span class="erp-menu-bar__link erp-menu-bar__link--pending" title="Em breve" aria-disabled="true">
            <span><?php echo e($item['label']); ?></span>
            <span class="erp-menu-bar__badge">Em breve</span>
        </span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/menu-bar-items.blade.php ENDPATH**/ ?>