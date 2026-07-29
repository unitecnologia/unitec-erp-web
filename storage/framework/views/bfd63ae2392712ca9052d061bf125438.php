<?php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
        'importado' => 'Importado',
    ];
?>

<div class="erp-orcamentos__tabs-wrap">
    <div class="erp-orcamentos__tabs">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $statusTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button
                type="button"
                wire:click="setStatusFilter('<?php echo e($value); ?>')"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'erp-orcamentos__tab',
                    'erp-orcamentos__tab--active' => $this->statusFilter === $value,
                    'erp-orcamentos__tab--todos' => $value === 'todos',
                    'erp-orcamentos__tab--aberto' => $value === 'aberto',
                    'erp-orcamentos__tab--fechado' => $value === 'fechado',
                    'erp-orcamentos__tab--cancelado' => $value === 'cancelado',
                    'erp-orcamentos__tab--importado' => $value === 'importado',
                ]); ?>"
            ><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/orcamentos/tabs.blade.php ENDPATH**/ ?>