<?php
    $recentSales = $recentSales ?? [];
?>

<article class="erp-dash-panel erp-dash__sales">
    <header class="erp-dash-panel__head">
        <h2 class="erp-dash-panel__title">Últimas vendas</h2>
        <span class="erp-dash-panel__meta"><?php echo e(count($recentSales) > 0 ? count($recentSales) . ' registros' : 'Sem vendas recentes'); ?></span>
    </header>
    <div class="erp-dash-panel__body erp-dash-panel__body--flush">
        <div class="erp-dash-table-wrap">
            <table class="erp-dash-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentSales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($sale['id']); ?></td>
                            <td><?php echo e($sale['cliente']); ?></td>
                            <td class="erp-dash-table__money"><?php echo e($sale['valor']); ?></td>
                            <td><?php echo e($sale['data']); ?></td>
                            <td>
                                <span class="erp-dash-badge erp-dash-badge--<?php echo e(\Illuminate\Support\Str::slug($sale['status'])); ?>">
                                    <?php echo e($sale['status']); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="erp-dash-table__empty">Nenhuma venda registrada.</td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</article>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/partials/sales-list.blade.php ENDPATH**/ ?>