<?php
    use App\Support\Erp\ErpSystemConfig;

    $fontSizePx = ErpSystemConfig::uiFontSizePx();
?>
<meta name="erp-ui-font-size" content="<?php echo e($fontSizePx); ?>">
<style id="erp-ui-density">
    html {
        font-size: <?php echo e($fontSizePx); ?>px;
    }
</style>
<script>
    document.documentElement.dataset.erpFontSize = <?php echo json_encode((string) $fontSizePx, 15, 512) ?>;
</script>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/ui-density.blade.php ENDPATH**/ ?>