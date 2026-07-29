<?php
    $canUpdate = \App\Support\Erp\ErpAccess::currentCan('ajusta_preco.update');
    $rows = $this->precosPainel;
?>

<div class="erp-ajusta-precos__prices">
    <div class="erp-ajusta-precos__section-bar" role="heading" aria-level="2">Preços</div>

    <div class="erp-ajusta-precos__prices-table-wrap">
        <table class="erp-ajusta-precos__prices-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>% Lucro Varejo</th>
                    <th>Preço Varejo</th>
                    <th>% Lucro Atacado</th>
                    <th>Preço Atacado</th>
                    <th>% Lucro Especial</th>
                    <th>Preço Especial</th>
                    <th>Origem</th>
                    <th>CSOSN</th>
                    <th>CST</th>
                    <th>% ICMS</th>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUpdate): ?>
                        <th></th>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr wire:key="ajp-price-<?php echo e($row['product_id']); ?>-<?php echo e($row['empresa_id']); ?>-<?php echo e($index); ?>">
                        <td class="erp-ajusta-precos__prices-empresa"><?php echo e($row['empresa']); ?></td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.pct_lucro" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.preco_venda" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.pct_lucro_atacado" <?php if(! $canUpdate): echo 'disabled'; endif; ?> title="Calculado a partir do custo; ao salvar usa o preço atacado">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.preco_atacado" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.pct_lucro_especial" <?php if(! $canUpdate): echo 'disabled'; endif; ?> title="Calculado a partir do custo; ao salvar usa o preço especial">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input" wire:model="precosPainel.<?php echo e($index); ?>.preco_especial" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="text" maxlength="1" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--xs" wire:model="precosPainel.<?php echo e($index); ?>.origem" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="text" maxlength="3" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.<?php echo e($index); ?>.csosn" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="text" maxlength="3" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.<?php echo e($index); ?>.cst_icms" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="erp-ajusta-precos__prices-input erp-ajusta-precos__prices-input--sm" wire:model="precosPainel.<?php echo e($index); ?>.aliq_icms" <?php if(! $canUpdate): echo 'disabled'; endif; ?>>
                        </td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUpdate): ?>
                            <td>
                                <button
                                    type="button"
                                    class="erp-ajusta-precos__prices-save"
                                    wire:click="salvarPrecoPainel(<?php echo e($index); ?>)"
                                    title="Salvar preços desta linha"
                                >
                                    Salvar
                                </button>
                            </td>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr class="erp-ajusta-precos__prices-empty-row">
                        <td colspan="<?php echo e($canUpdate ? 12 : 11); ?>">
                            <span class="erp-ajusta-precos__prices-empty">Selecione um produto na grade para ver e editar os preços.</span>
                        </td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/ajusta-precos/prices-panel.blade.php ENDPATH**/ ?>