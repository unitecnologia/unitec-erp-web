<?php
    $alerts = $alerts ?? [];
    $important = $alerts['important'] ?? [];
    $boletos = $alerts['boletos'] ?? [];
    $estoque = $alerts['estoque'] ?? [];
?>

<aside class="erp-dash__aside" aria-label="Alertas">
    <article class="erp-dash-panel erp-dash-panel--alerts">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Alertas importantes</h2>
            <span class="erp-dash-panel__meta"><?php echo e(count($important)); ?></span>
        </header>
        <ul class="erp-dash-alert-list">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $important; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'erp-dash-alert',
                    'erp-dash-alert--' . $alert['tone'],
                    'erp-dash-alert--featured' => ! empty($alert['featured']),
                    'erp-dash-alert--blink' => ! empty($alert['blink']),
                ]); ?>">
                    <span class="erp-dash-alert__title"><?php echo e($alert['title']); ?></span>
                    <span class="erp-dash-alert__time"><?php echo e($alert['time']); ?></span>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li class="erp-dash-alert erp-dash-alert--empty">
                    <span class="erp-dash-alert__title">Nenhum alerta no momento</span>
                </li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Boletos vencidos</h2>
            <span class="erp-dash-panel__meta"><?php echo e(count($boletos)); ?></span>
        </header>
        <ul class="erp-dash-mini-list">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $boletos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $boleto): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title"><?php echo e($boleto['cliente']); ?></span>
                    <span class="erp-dash-mini-list__meta">
                        <strong class="erp-dash-mini-list__amount"><?php echo e($boleto['valor']); ?></strong>
                        <span><?php echo e($boleto['vencimento']); ?></span>
                    </span>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li class="erp-dash-mini-list__item erp-dash-mini-list__item--empty">
                    <span class="erp-dash-mini-list__title">Nenhum boleto vencido</span>
                </li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
    </article>

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Estoque mínimo</h2>
            <span class="erp-dash-panel__meta"><?php echo e(count($estoque)); ?></span>
        </header>
        <ul class="erp-dash-mini-list">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $estoque; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li class="erp-dash-mini-list__item">
                    <span class="erp-dash-mini-list__title"><?php echo e($item['produto']); ?></span>
                    <span class="erp-dash-mini-list__meta">Atual <?php echo e($item['atual']); ?> · Mín. <?php echo e($item['minimo']); ?></span>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li class="erp-dash-mini-list__item erp-dash-mini-list__item--empty">
                    <span class="erp-dash-mini-list__title">Estoque dentro do mínimo</span>
                </li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
    </article>
</aside>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/partials/alerts-sidebar.blade.php ENDPATH**/ ?>