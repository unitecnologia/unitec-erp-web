<?php
    $sub = $this->activeEstoqueSubTab;
?>

<div class="erp-produtos-estoque-panel">
    <div class="erp-produtos-estoque-panel__tabs" role="tablist" aria-label="Estoque e complementos">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
            'estoques' => 'Estoques',
            'localizacoes' => 'Localizações',
            'trocas' => 'Trocas',
            'dados_anp' => 'Dados ANP',
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button
                type="button"
                role="tab"
                wire:click="setActiveEstoqueSubTab('<?php echo e($key); ?>')"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'erp-produtos-estoque-panel__tab',
                    'erp-produtos-estoque-panel__tab--active' => $sub === $key,
                ]); ?>"
                aria-selected="<?php echo e($sub === $key ? 'true' : 'false'); ?>"
            ><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="erp-produtos-estoque-panel__body" wire:key="estoque-sub-<?php echo e($sub); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sub === 'estoques'): ?>
            <div class="erp-produtos-estoques" wire:key="estoque-panel-estoques">
                <div class="erp-produtos-estoques__grid-wrap">
                    <table class="erp-produtos-estoques__grid">
                        <thead>
                            <tr>
                                <th class="erp-produtos-estoques__col-nome">Estoque</th>
                                <th class="erp-produtos-estoques__col-num">Atual</th>
                                <th class="erp-produtos-estoques__col-num">Reservado</th>
                                <th class="erp-produtos-estoques__col-num">Disponível</th>
                                <th class="erp-produtos-estoques__col-num">Condicional</th>
                                <th class="erp-produtos-estoques__col-num">Previsto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->productEstoquePosicoes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr wire:key="estoque-pos-<?php echo e($row['key']); ?>">
                                    <td class="erp-produtos-estoques__col-nome" title="<?php echo e($row['nome']); ?>"><?php echo e($row['nome']); ?></td>
                                    <td class="erp-produtos-estoques__col-num"><?php echo e($row['atual']); ?></td>
                                    <td class="erp-produtos-estoques__col-num erp-produtos-estoques__num--reserved"><?php echo e($row['reservado']); ?></td>
                                    <td class="erp-produtos-estoques__col-num erp-produtos-estoques__num--available"><?php echo e($row['disponivel']); ?></td>
                                    <td class="erp-produtos-estoques__col-num"><?php echo e($row['condicional']); ?></td>
                                    <td class="erp-produtos-estoques__col-num"><?php echo e($row['previsto']); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="6" class="erp-produtos-estoques__empty">Nenhum estoque disponível.</td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="erp-produtos-estoques__col-nome">Total</td>
                                <td class="erp-produtos-estoques__col-num"><?php echo e($this->productEstoqueTotais['atual']); ?></td>
                                <td class="erp-produtos-estoques__col-num erp-produtos-estoques__num--reserved"><?php echo e($this->productEstoqueTotais['reservado']); ?></td>
                                <td class="erp-produtos-estoques__col-num erp-produtos-estoques__num--available"><?php echo e($this->productEstoqueTotais['disponivel']); ?></td>
                                <td class="erp-produtos-estoques__col-num"><?php echo e($this->productEstoqueTotais['condicional']); ?></td>
                                <td class="erp-produtos-estoques__col-num"><?php echo e($this->productEstoqueTotais['previsto']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php echo $__env->make('filament.components.erp.produtos.form.reservas-ativas', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        <?php elseif($sub === 'localizacoes'): ?>
            <div class="erp-produtos-estoque-panel__localizacoes" wire:key="estoque-panel-localizacoes">
                <p class="erp-produtos-estoques__hint">
                    Localização física do produto no depósito da loja ativa. Com multi-loja, cada estoque terá a própria localização.
                </p>
                <?php echo $__env->make('filament.components.erp.produtos.form.localizacao-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        <?php elseif($sub === 'trocas'): ?>
            <div class="erp-produtos-estoques" wire:key="estoque-panel-trocas">
                <p class="erp-produtos-estoques__hint">
                    Trocas / códigos de fornecedor vinculados a este produto.
                </p>
                <div class="erp-produtos-estoques__grid-wrap">
                    <table class="erp-produtos-estoques__grid">
                        <thead>
                            <tr>
                                <th style="width: 6rem;">Cód. Fornc.</th>
                                <th>Fornecedor</th>
                                <th class="erp-produtos-estoques__col-num">Qntd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="erp-produtos-estoques__empty">&lt;nenhuma troca para este produto&gt;</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="erp-produtos-anp" wire:key="estoque-panel-anp">
                <p class="erp-produtos-estoques__hint">
                    Dados ANP para combustíveis. Campos extras da reforma fiscal entram aqui depois.
                </p>
                <div class="erp-produtos-anp__grid">
                    <div class="erp-produtos-anp__field erp-produtos-anp__field--code">
                        <label for="pprod-anp-code">Código ANP</label>
                        <input id="pprod-anp-code" type="text" wire:model="data.anp_code" maxlength="20" class="erp-pcad-form__input">
                    </div>
                    <div class="erp-produtos-anp__field erp-produtos-anp__field--wide">
                        <label for="pprod-anp-peso-liq">Peso Líq.</label>
                        <input id="pprod-anp-peso-liq" type="text" wire:model="data.peso_liq" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-form__input--num">
                    </div>
                    <div class="erp-produtos-anp__field">
                        <label for="pprod-anp-glp">%pGLP</label>
                        <input id="pprod-anp-glp" type="text" wire:model="data.glp_pct" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
                    </div>
                    <div class="erp-produtos-anp__field">
                        <label for="pprod-anp-gnn">%pGNn</label>
                        <input id="pprod-anp-gnn" type="text" wire:model="data.gnn_pct" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
                    </div>
                    <div class="erp-produtos-anp__field">
                        <label for="pprod-anp-gni">%pGNi</label>
                        <input id="pprod-anp-gni" type="text" wire:model="data.gni_pct" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
                    </div>
                    <div class="erp-produtos-anp__field">
                        <label for="pprod-anp-issqn">ISSQN %</label>
                        <input id="pprod-anp-issqn" type="text" wire:model="data.issqn" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
                    </div>
                </div>
                <p class="erp-produtos-anp__note">GLP + GNn + GNi = 100% quando informado. %adRem / vPart serão adicionados na evolução fiscal.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/tabs/estoques.blade.php ENDPATH**/ ?>