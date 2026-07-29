<?php
    $highlights = $highlights ?? [];
?>

<article class="erp-dash-panel erp-dash__highlights">
    <header class="erp-dash-panel__head">
        <h2 class="erp-dash-panel__title">Destaques do mês</h2>
        <span class="erp-dash-panel__meta">mês</span>
    </header>
    <div class="erp-dash-panel__body erp-dash__highlights-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $highlights; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="erp-dash-highlight">
                <span class="erp-dash-highlight__label"><?php echo e($item['label'] ?? ''); ?></span>
                <strong class="erp-dash-highlight__value"><?php echo e($item['value'] ?? '—'); ?></strong>
                <span class="erp-dash-highlight__hint"><?php echo e($item['hint'] ?? ''); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="erp-dash__highlights-empty">Sem dados no período.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</article>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/partials/highlights.blade.php ENDPATH**/ ?>