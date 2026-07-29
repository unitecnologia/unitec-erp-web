<?php
    use App\Support\Erp\ErpMenu;

    $menus = ErpMenu::mainMenus();
?>

<nav class="erp-menu-bar" aria-label="Menu principal">
    <ul class="erp-menu-bar__list">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $menus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="erp-menu-bar__item">
                <div class="erp-menu-bar__details">
                    <button type="button" class="erp-menu-bar__trigger"><?php echo e($menu['label']); ?></button>
                    <div class="erp-menu-bar__dropdown">
                        <?php echo $__env->make('filament.components.erp.menu-bar-items', ['items' => $menu['items']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                </div>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
</nav>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/menu-bar.blade.php ENDPATH**/ ?>