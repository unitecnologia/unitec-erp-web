<?php
    $tipoTabs = [
        'clientes' => 'Clientes',
        'funcionarios' => 'Funcionários',
        'fornecedores' => 'Fornecedores',
        'administradoras' => 'Administradoras',
        'parceiros' => 'Parceiros',
        'todos' => 'Todos',
    ];
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! in_array($this->tipoFilter, ['ccf_spc'], true)): ?>
    <div class="erp-pessoas__tabs-wrap">
        <div class="erp-pessoas__tabs erp-pessoas__tabs--tipo">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $tipoTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button
                    type="button"
                    wire:click="setTipoFilter('<?php echo e($value); ?>')"
                    class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-pessoas__tab', 'erp-pessoas__tab--active' => $this->tipoFilter === $value]); ?>"
                ><?php echo e($label); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/tabs.blade.php ENDPATH**/ ?>