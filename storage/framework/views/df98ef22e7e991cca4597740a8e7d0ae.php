<?php if (! $__env->hasRenderedOnce('erp-form-scripts-styles')): $__env->markAsRenderedOnce('erp-form-scripts-styles'); ?>
<?php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
?>

<link rel="stylesheet" href="<?php echo e(asset('vendor/flatpickr/flatpickr.min.css')); ?>?v=<?php echo e($version); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/erp-datepicker.css')); ?>?v=<?php echo e($version); ?>">
<?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/form-scripts-styles.blade.php ENDPATH**/ ?>