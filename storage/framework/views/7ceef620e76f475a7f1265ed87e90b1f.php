<?php
    use App\Support\Erp\ErpAssetVersion;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
?>

<script src="<?php echo e(asset('js/erp-shell.js')); ?>?v=<?php echo e($version); ?>-<?php echo e(config('unitec.versao')); ?>"></script>
<script>
    window.ErpDeviceConfig = {
        baseUrl: <?php echo json_encode(config('unitec.device_service.base_url', 'http://127.0.0.1:9330'), 512) ?>,
        apiKey: <?php echo json_encode(config('unitec.device_service.api_key', ''), 512) ?>,
        timeoutMs: <?php echo e((int) config('unitec.device_service.timeout_ms', 2500)); ?>

    };
</script>
<script src="<?php echo e(asset('js/erp-device-service.js')); ?>?v=<?php echo e($version); ?>"></script>
<script src="<?php echo e(asset('js/erp-silent-print.js')); ?>?v=<?php echo e($version); ?>"></script>
<?php echo $__env->make('filament.components.erp.form-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset('js/erp-precif-enter-v5.js')); ?>?v=<?php echo e($version); ?>"></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->is('admin/orcamentos*')): ?>
    <script src="<?php echo e(asset('js/erp-orcamentos.js')); ?>?v=<?php echo e($version); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/shell-scripts.blade.php ENDPATH**/ ?>