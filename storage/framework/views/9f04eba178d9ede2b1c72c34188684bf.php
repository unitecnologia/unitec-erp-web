<?php echo $__env->make('reports.partials.recibo-document-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="recibo-doc">
    <header class="recibo-doc__header">
        <div class="recibo-doc__brand">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($logoDataUri) || ! empty($logoUrl)): ?>
                <img
                    src="<?php echo e($logoDataUri ?? $logoUrl); ?>"
                    alt="Logo"
                    class="recibo-doc__logo"
                >
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div>
                <div class="recibo-doc__empresa"><?php echo e(mb_strtoupper((string) ($empresa?->razao_social ?? $empresa?->fantasia ?? 'EMPRESA'), 'UTF-8')); ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($empresaEndereco ?? null)): ?>
                    <div class="recibo-doc__endereco"><?php echo e($empresaEndereco); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($empresa?->cnpj ?? null)): ?>
                    <div class="recibo-doc__meta">CNPJ: <?php echo e($empresa->cnpj); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div class="recibo-doc__title-block">
            <div class="recibo-doc__title">RECIBO</div>
            <div class="recibo-doc__codigo">Nº <?php echo e(str_pad((string) $recibo->codigo, 6, '0', STR_PAD_LEFT)); ?></div>
        </div>
    </header>

    <div class="recibo-doc__valor-box">
        <span class="recibo-doc__valor-label">Valor</span>
        <span class="recibo-doc__valor">R$ <?php echo e($recibo->valorFormatado()); ?></span>
    </div>

    <p class="recibo-doc__texto">
        Recebi(emos) de <strong><?php echo e(mb_strtoupper((string) $recibo->recebi_de, 'UTF-8')); ?></strong>
        a importância de <strong><?php echo e(mb_strtolower((string) $extenso, 'UTF-8')); ?></strong>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($recibo->referente_a)): ?>
            referente a <strong><?php echo e(mb_strtoupper((string) $recibo->referente_a, 'UTF-8')); ?></strong>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        .
    </p>

    <div class="recibo-doc__rodape">
        <div class="recibo-doc__data">
            <?php echo e(($empresa?->cidade ? mb_strtoupper($empresa->cidade, 'UTF-8').', ' : '')); ?><?php echo e(optional($recibo->emissao)->format('d/m/Y')); ?>

        </div>
        <div class="recibo-doc__assinatura">
            <div class="recibo-doc__linha"></div>
            <div class="recibo-doc__assinatura-label">Assinatura</div>
        </div>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/reports/partials/recibo-document-body.blade.php ENDPATH**/ ?>