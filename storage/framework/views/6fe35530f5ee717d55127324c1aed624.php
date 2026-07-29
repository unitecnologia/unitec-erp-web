<?php
    $tone = $tone ?? 'warning';
    $titleId = $titleId ?? 'erp-aviso-modal-title';
    $icon = $icon ?? '!';
    $lines = $lines ?? [];
    $hint = $hint ?? null;
    $primaryLabel = $primaryLabel ?? 'OK';
    $primaryAction = $primaryAction ?? null;
    $secondaryLabel = $secondaryLabel ?? null;
    $secondaryAction = $secondaryAction ?? null;
    $escapeAction = $escapeAction ?? null;
    $backdropAction = $backdropAction ?? $secondaryAction;
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($open ?? false): ?>
    <div
        class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-aviso-modal', 'erp-aviso-modal--' . $tone]); ?>"
        role="dialog"
        aria-modal="true"
        aria-labelledby="<?php echo e($titleId); ?>"
        <?php if($escapeAction): ?> wire:keydown.escape="<?php echo e($escapeAction); ?>" <?php endif; ?>
    >
        <div
            class="erp-aviso-modal__backdrop"
            <?php if($backdropAction): ?> wire:click="<?php echo e($backdropAction); ?>" <?php endif; ?>
        ></div>

        <div class="erp-aviso-modal__box">
            <div class="erp-aviso-modal__icon" aria-hidden="true"><?php echo e($icon); ?></div>

            <h2 class="erp-aviso-modal__title" id="<?php echo e($titleId); ?>"><?php echo e($title ?? 'Aviso'); ?></h2>

            <div class="erp-aviso-modal__body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p class="erp-aviso-modal__text"><?php echo $line; ?></p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($extraView)): ?>
                    <div class="erp-aviso-modal__extra">
                        <?php echo $__env->make($extraView, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="erp-aviso-modal__actions">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($primaryAction): ?>
                    <button
                        type="button"
                        wire:click="<?php echo e($primaryAction); ?>"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                    ><?php echo e($primaryLabel); ?></button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($secondaryAction && $secondaryLabel): ?>
                    <button
                        type="button"
                        wire:click="<?php echo e($secondaryAction); ?>"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                    ><?php echo e($secondaryLabel); ?></button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hint): ?>
                <p class="erp-aviso-modal__hint"><?php echo e($hint); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/aviso-modal.blade.php ENDPATH**/ ?>