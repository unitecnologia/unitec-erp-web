<?php
    use App\Support\Erp\ErpAssetVersion;
    use App\Support\Erp\ErpPageAssets;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
?>

<script>
    window.__erpUpdateConfig = {
        launchUrl: '/admin/erp-update/launch',
        statusUrl: '/admin/erp-update/status',
        resetUrl: '/admin/erp-update/reset',
        assetVersion: <?php echo json_encode($version, 15, 512) ?>,
        appVersion: <?php echo json_encode(config('unitec.versao'), 15, 512) ?>,
        zipName: <?php echo json_encode(config('unitec.update_zip_name', 'Unitec-ERP-Update.zip'), 512) ?>,
        stallSeconds: 180,
        downloadStallSeconds: 900,
        applyingStallSeconds: 600,
        migratingStallSeconds: 1200,
        finalizingStallSeconds: 300,
        maxMinutes: 45,
    };
</script>
<meta name="erp-asset-version" content="<?php echo e($version); ?>-<?php echo e(config('unitec.versao')); ?>">
<?php echo $__env->make('filament.components.erp.no-browser-hints', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset('js/erp-compras.js')); ?>?v=<?php echo e($version); ?>" defer></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ErpPageAssets::resourceSegment() === 'nfe'): ?>
    <script src="<?php echo e(asset('js/erp-nfe-lancamento.js')); ?>?v=<?php echo e($version); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ErpPageAssets::resourceSegment() === 'notas-fornecedores'): ?>
    <script src="<?php echo e(asset('js/erp-notas-fornecedores.js')); ?>?v=<?php echo e($version); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ErpPageAssets::resourceSegment() === 'products'): ?>
    <script src="<?php echo e(asset('js/erp-precif-enter-v5.js')); ?>?v=<?php echo e($version); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ErpPageAssets::routeKind() === 'dashboard'): ?>
    <script src="<?php echo e(asset('js/vendor/chart.umd.min.js')); ?>?v=<?php echo e($version); ?>"></script>
    <script src="<?php echo e(asset('js/erp-home-charts.js')); ?>?v=<?php echo e($version); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/head-assets.blade.php ENDPATH**/ ?>