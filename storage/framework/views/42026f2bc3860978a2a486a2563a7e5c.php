<?php
    use App\Support\Erp\ErpAssetVersion;

    $version = ErpAssetVersion::bundle();
?>

<script src="<?php echo e(asset('js/erp-no-browser-hints.js')); ?>?v=<?php echo e($version); ?>-<?php echo e(config('unitec.versao')); ?>"></script>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/no-browser-hints.blade.php ENDPATH**/ ?>