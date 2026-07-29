<?php ($record = $getRecord()); ?>

<input
    type="checkbox"
    class="erp-ajusta-precos__check"
    value="<?php echo e($record->getKey()); ?>"
    <?php if(in_array((string) $record->getKey(), $this->selecionados, true)): echo 'checked'; endif; ?>
    wire:click.prevent.stop="toggleSelecionado(<?php echo e($record->getKey()); ?>)"
    wire:key="ajp-sel-<?php echo e($record->getKey()); ?>-<?php echo e(in_array((string) $record->getKey(), $this->selecionados, true) ? '1' : '0'); ?>"
    title="Marcar para aplicação em lote"
>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/ajusta-precos/select-cell.blade.php ENDPATH**/ ?>