<?php
    $nome = trim((string) ($nome ?? ''));
    $prefix = $prefix ?? 'erp-empresa';
?>

<div
    class="<?php echo e($prefix); ?>__empresa-group erp-empresa-badge"
    role="status"
    aria-label="Empresa ativa<?php echo e($nome !== '' ? ': '.$nome : ''); ?>"
>
    <span class="<?php echo e($prefix); ?>__empresa-label erp-empresa-badge__label">Empresa</span>
    <span class="erp-empresa-badge__sep" aria-hidden="true">·</span>
    <span
        class="<?php echo e($prefix); ?>__empresa-value erp-empresa-badge__value"
        <?php if($nome !== ''): ?> title="<?php echo e($nome); ?>" <?php endif; ?>
    ><?php echo e($nome !== '' ? $nome : '—'); ?></span>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/empresa-badge.blade.php ENDPATH**/ ?>