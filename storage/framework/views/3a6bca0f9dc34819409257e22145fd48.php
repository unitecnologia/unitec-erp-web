<?php
    use App\Support\Erp\ErpContext;

    $statusItems = filament()->auth()->check() ? ErpContext::statusBar() : [];
?>

<div class="erp-title-bar">
    <div class="erp-title-bar__brand">
        <span class="erp-title-bar__app"><?php echo e(config('unitec.app_name')); ?></span>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusItems !== []): ?>
        <div class="erp-title-bar__status" aria-label="Barra de status">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $statusItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="erp-title-bar__status-item">
                    <span class="erp-title-bar__status-label"><?php echo e($label); ?>:</span>
                    <span
                        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'erp-title-bar__status-value',
                            'erp-title-bar__status-value--accent' => $label === 'Empresa',
                        ]); ?>"
                        <?php if($label === 'Atualizado Em'): ?> id="erp-status-updated-at" <?php endif; ?>
                    ><?php echo e($value); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="erp-title-bar__user">
        <span class="erp-title-bar__user-label">Usuário</span>
        <span class="erp-title-bar__username"><?php echo e(filament()->auth()->user()?->name); ?></span>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/title-bar.blade.php ENDPATH**/ ?>