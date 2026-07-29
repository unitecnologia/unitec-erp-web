<?php
    use App\Support\Erp\ErpAssetVersion;

    $jsVersion = ErpAssetVersion::bundle();
?>

<script>
    window.__erpListConfigs = window.__erpListConfigs || [];
    window.__erpListConfigs.push(<?php echo json_encode($config, 15, 512) ?>);
</script>
<script src="<?php echo e(asset('js/erp-uppercase.js')); ?>?v=<?php echo e($jsVersion); ?>" defer></script>
<script src="<?php echo e(asset('js/erp-list.js')); ?>?v=<?php echo e($jsVersion); ?>" defer></script><?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/list-scripts.blade.php ENDPATH**/ ?>