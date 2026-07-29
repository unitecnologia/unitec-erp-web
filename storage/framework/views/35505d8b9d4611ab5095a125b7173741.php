<?php
    use App\Support\Erp\Reports\ComissaoVendedoresReport as Rel;
    $money = fn ($v): string => Rel::formatMoney((float) $v);
?>

<div class="comissao-doc">
    <div class="comissao-doc__frame">
        <div class="comissao-doc__header">
            <div class="comissao-doc__logo-cell">
                <div class="comissao-doc__logo">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($logoDataUri ?? null)): ?>
                        <img src="<?php echo e($logoDataUri); ?>" alt="Logomarca">
                    <?php elseif(filled($logoUrl ?? null)): ?>
                        <img src="<?php echo e($logoUrl); ?>" alt="Logomarca">
                    <?php else: ?>
                        <span class="comissao-doc__logo-fallback">U</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="comissao-doc__company-cell">
                <span class="comissao-doc__company-name"><?php echo e(mb_strtoupper($empresa?->nome ?? 'UNITECNOLOGIA SISTEMAS', 'UTF-8')); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($empresa?->responsavel)): ?>
                    <span><?php echo e(mb_strtoupper($empresa->responsavel, 'UTF-8')); ?><br></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($empresaEndereco ?? null)): ?>
                    <span><?php echo e($empresaEndereco); ?><br></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span>
                    FONE: <?php echo e($empresa?->telefone ?: ''); ?>&nbsp;&nbsp;EMAIL: <?php echo e($empresa?->email ?: ''); ?>

                </span>
            </div>
        </div>

        <hr class="comissao-doc__rule">

        <div class="comissao-doc__title"><?php echo e($reportTitle ?? 'COMISSÃO DE OPERADORES'); ?></div>

        <div class="comissao-doc__filters">
            <span>| PERÍODO: <?php echo e(mb_strtoupper($periodoLabel ?? '', 'UTF-8')); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($filters['vendedor'] ?? 'todos') !== 'todos'): ?>
                <span>| OPERADOR: <?php echo e(mb_strtoupper($filterOptions['vendedor'][$filters['vendedor']] ?? (string) $filters['vendedor'], 'UTF-8')); ?></span>
            <?php else: ?>
                <span>| OPERADOR: TODOS</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <table class="comissao-doc__table">
            <thead>
                <tr>
                    <th>Operador</th>
                    <th class="num">Qtd</th>
                    <th class="num">Vendas à Vista</th>
                    <th class="num">% AV</th>
                    <th class="num">Comissão à Vista</th>
                    <th class="num">Vendas a Prazo</th>
                    <th class="num">% AP</th>
                    <th class="num">Comissão a Prazo</th>
                    <th class="num">Total Vendido</th>
                    <th class="num">Comissão Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $linhas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($l['nome']); ?></td>
                        <td class="num"><?php echo e($l['qtd']); ?></td>
                        <td class="num"><?php echo e($money($l['total_avista'])); ?></td>
                        <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $l['comissao_av'], 2, ',', '.'), '0'), ',')); ?>%</td>
                        <td class="num"><?php echo e($money($l['comissao_avista'])); ?></td>
                        <td class="num"><?php echo e($money($l['total_aprazo'])); ?></td>
                        <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $l['comissao_ap'], 2, ',', '.'), '0'), ',')); ?>%</td>
                        <td class="num"><?php echo e($money($l['comissao_aprazo'])); ?></td>
                        <td class="num"><?php echo e($money($l['total_geral'])); ?></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($l['comissao_total'])); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="10" class="comissao-doc__empty">Nenhuma venda faturada no período.</td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($linhas)): ?>
                <tfoot>
                    <tr>
                        <td class="comissao-doc__strong">TOTAL</td>
                        <td class="num comissao-doc__strong"><?php echo e($totais['qtd']); ?></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['total_avista'])); ?></td>
                        <td></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['comissao_avista'])); ?></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['total_aprazo'])); ?></td>
                        <td></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['comissao_aprazo'])); ?></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['total_geral'])); ?></td>
                        <td class="num comissao-doc__strong"><?php echo e($money($totais['comissao_total'])); ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </table>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/reports/partials/comissao-vendedores-document-body.blade.php ENDPATH**/ ?>