<?php
    use App\Support\Erp\ErpAssetVersion;
    use App\Support\Erp\ErpPageAssets;

    $version = ErpAssetVersion::bundle();

    $stylesheets = [
        'css/erp-shell.css',
        ...ErpPageAssets::coreStylesheets(),
    ];
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $stylesheets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stylesheet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
        $stylesheetPath = public_path($stylesheet);
        $stylesheetVersion = file_exists($stylesheetPath) ? (string) filemtime($stylesheetPath) : $version;
    ?>
    <link rel="stylesheet" href="<?php echo e(asset($stylesheet)); ?>?v=<?php echo e($stylesheetVersion); ?>">
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('filament.components.erp.form-scripts-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/authenticated-core-styles.blade.php ENDPATH**/ ?>