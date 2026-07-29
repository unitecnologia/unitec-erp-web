<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->record?->exists): ?>
    <div class="erp-produtos-form__reservas">
        <div class="erp-produtos-form__reservas-summary">
            <span class="erp-produtos-form__reservas-chip erp-produtos-form__reservas-chip--reserved">
                Reservado (app): <strong><?php echo e($this->productEstoqueReservadoLabel); ?></strong>
            </span>
            <span class="erp-produtos-form__reservas-chip erp-produtos-form__reservas-chip--available">
                Disponível: <strong><?php echo e($this->productEstoqueDisponivelLabel); ?></strong>
            </span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($this->productReservasAtivas) > 0): ?>
                <details class="erp-produtos-form__reservas-details">
                    <summary><?php echo e(count($this->productReservasAtivas)); ?> reserva(s) ativa(s) — ver detalhes</summary>
                    <div class="erp-produtos-form__reservas-table-wrap">
                        <table class="erp-produtos-form__reservas-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Plat.</th>
                                    <th>Qtd</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->productReservasAtivas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e($row['pedido']); ?></td>
                                        <td><?php echo e($row['cliente']); ?></td>
                                        <td><?php echo e($row['vendedor']); ?></td>
                                        <td><?php echo e($row['plataforma']); ?></td>
                                        <td class="erp-produtos-form__reservas-num"><?php echo e($row['quantidade']); ?></td>
                                        <td><?php echo e($row['data']); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php else: ?>
                <span class="erp-produtos-form__reservas-empty">Sem reservas ativas no app</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/reservas-ativas.blade.php ENDPATH**/ ?>