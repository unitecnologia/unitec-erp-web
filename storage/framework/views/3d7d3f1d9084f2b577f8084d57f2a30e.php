<?php if (! $__env->hasRenderedOnce('erp-form-scripts')): $__env->markAsRenderedOnce('erp-form-scripts'); ?>
<?php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
?>

<?php echo $__env->make('filament.components.erp.form-scripts-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<script>
(function () {
    const selector = 'input[type="date"][data-erp-date-wire], input[type="date"][data-wire-field], input[type="date"][data-erp-date], input[type="date"][data-mask="date-br"]';

    function prepDateInputs(root) {
        if (! root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll(selector).forEach((input) => {
            if (input.dataset.erpDatePrepped === '1') {
                return;
            }

            if (input.hasAttribute('data-erp-native-date') || input.dataset.erpDateSkip === '1') {
                return;
            }

            input.type = 'text';
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('placeholder', 'dd/mm/aaaa');
            input.classList.add('erp-date-input');
            input.dataset.erpDatePrepped = '1';
        });
    }

    window.__erpPrepDateInputs = prepDateInputs;
    prepDateInputs(document);
    document.addEventListener('DOMContentLoaded', () => prepDateInputs(document));
    document.addEventListener('livewire:navigated', () => prepDateInputs(document));
})();
</script>

<script src="<?php echo e(asset('vendor/flatpickr/flatpickr.min.js')); ?>?v=<?php echo e($version); ?>"></script>
<script src="<?php echo e(asset('vendor/flatpickr/pt.js')); ?>?v=<?php echo e($version); ?>"></script>
<script src="<?php echo e(asset('js/erp-masks.js')); ?>?v=<?php echo e($version); ?>"></script>
<script src="<?php echo e(asset('js/erp-datepicker.js')); ?>?v=<?php echo e($version); ?>"></script>
<script src="<?php echo e(asset('js/erp-uppercase.js')); ?>?v=<?php echo e($version); ?>"></script>
<?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/form-scripts.blade.php ENDPATH**/ ?>