<?php
    $statusTabs = [
        'todos' => 'Todos',
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
    ];
?>

<div class="erp-cfop__status-wrap">
    <div class="erp-cfop__status">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $statusTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button
                type="button"
                wire:click="setStatusFilter('<?php echo e($value); ?>')"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'erp-cfop__tab',
                    'erp-cfop__tab--active' => $this->statusFilter === $value,
                ]); ?>"
            ><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/cfops/status-tabs.blade.php ENDPATH**/ ?>