<?php
    use App\Models\NotaFornecedor;

    $statusTabs = [
        'todas' => 'Todas',
        NotaFornecedor::STATUS_PENDENTE => 'Pendentes',
        NotaFornecedor::STATUS_GEROU_COMPRAS => 'Gerou Compras',
        NotaFornecedor::STATUS_ACEITA => 'Aceitas',
        NotaFornecedor::STATUS_DESCONHECIDA => 'Desconhecidas',
    ];
?>

<div class="erp-nfe__tabs-wrap">
    <div class="erp-nfe__tabs">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $statusTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button
                type="button"
                wire:click="setStatusFilter('<?php echo e($value); ?>')"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'erp-nfe__tab',
                    'erp-nfe__tab--' . $value,
                    'erp-nfe__tab--active' => $this->statusFilter === $value,
                ]); ?>"
            ><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/tabs.blade.php ENDPATH**/ ?>