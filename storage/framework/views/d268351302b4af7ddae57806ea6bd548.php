<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->cclassTribModalOpen): ?>
    <?php
        $cclassAllowImport = array_key_exists('param_imp_cclass_trib', $this->data ?? []);
        $selectedCodigo = $this->cclassTribSelectedCodigo;
        $selectedRow = null;
        if (filled($selectedCodigo)) {
            foreach ($this->cclassTribRows as $row) {
                if (($row['codigo'] ?? null) === $selectedCodigo) {
                    $selectedRow = $row;
                    break;
                }
            }
        }
    ?>
    <div
        class="erp-lookup-modal erp-cclass-trib-modal"
        wire:keydown.escape.window="closeCclassTribModal"
        wire:keydown.f3.window="filtrarCclassTrib"
        <?php if(filled($selectedCodigo)): ?>
            wire:keydown.enter.window="applyCclassTribRow('<?php echo e($selectedCodigo); ?>')"
        <?php endif; ?>
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCclassTribModal"></div>

        <div
            class="erp-lookup-modal__window erp-cclass-trib-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-cclass-trib-title"
        >
            <header class="erp-cclass-trib-modal__header">
                <div class="erp-cclass-trib-modal__header-text">
                    <h2 id="erp-cclass-trib-title" class="erp-cclass-trib-modal__title">Classificação Tributária</h2>
                    <p class="erp-cclass-trib-modal__subtitle">
                        <?php echo e($cclassAllowImport
                            ? 'Consulte e selecione o cClassTrib (IBS/CBS) do imposto padrão'
                            : 'Consulte e selecione o cClassTrib (IBS/CBS) do produto'); ?>

                    </p>
                </div>
                <button
                    type="button"
                    class="erp-cclass-trib-modal__close"
                    wire:click="closeCclassTribModal"
                    title="Fechar (Esc)"
                    aria-label="Fechar"
                >✕</button>
            </header>

            <div class="erp-cclass-trib-modal__toolbar">
                <div class="erp-cclass-trib-modal__search">
                    <div class="erp-cclass-trib-modal__field">
                        <label for="cclass-filtro-codigo">Código / descrição</label>
                        <input
                            id="cclass-filtro-codigo"
                            type="text"
                            wire:model="cclassTribFiltroCodigo"
                            wire:keydown.enter="filtrarCclassTrib"
                            class="erp-cclass-trib-modal__input"
                            maxlength="80"
                            autocomplete="off"
                            placeholder="Ex.: 000001 ou automotivo"
                        >
                    </div>
                    <div class="erp-cclass-trib-modal__field erp-cclass-trib-modal__field--cst">
                        <label for="cclass-filtro-cst">CST</label>
                        <input
                            id="cclass-filtro-cst"
                            type="text"
                            wire:model="cclassTribFiltroCst"
                            wire:keydown.enter="filtrarCclassTrib"
                            class="erp-cclass-trib-modal__input"
                            maxlength="3"
                            autocomplete="off"
                            placeholder="000"
                        >
                    </div>
                    <button
                        type="button"
                        class="erp-cclass-trib-modal__btn erp-cclass-trib-modal__btn--filter"
                        wire:click="filtrarCclassTrib"
                        title="Filtrar (F3)"
                        <?php if($this->cclassTribImporting): echo 'disabled'; endif; ?>
                    >
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                        </svg>
                        <span>Filtrar</span>
                        <kbd>F3</kbd>
                    </button>
                </div>

                <div class="erp-cclass-trib-modal__chips" role="radiogroup" aria-label="Indicadores de nota">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                        'todos' => 'Todos',
                        'nfe' => 'NF-e',
                        'nfce' => 'NFC-e',
                        'nfse' => 'NFS-e',
                        'cte' => 'CT-e',
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'erp-cclass-trib-modal__chip',
                            'is-active' => ($this->cclassTribFiltroIndicador ?: 'todos') === $value,
                        ]); ?>">
                            <input
                                type="radio"
                                wire:model.live="cclassTribFiltroIndicador"
                                value="<?php echo e($value); ?>"
                            >
                            <span><?php echo e($label); ?></span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="erp-cclass-trib-modal__table-head">
                <span class="erp-cclass-trib-modal__table-title">Resultados</span>
                <span class="erp-cclass-trib-modal__count"><?php echo e(number_format(count($this->cclassTribRows), 0, ',', '.')); ?> registro(s)</span>
            </div>

            <div class="erp-cclass-trib-modal__grid-wrap">
                <table class="erp-cclass-trib-modal__grid">
                    <thead>
                        <tr>
                            <th class="erp-cclass-trib-modal__col-cst">CST</th>
                            <th class="erp-cclass-trib-modal__col-grupo">Situação</th>
                            <th class="erp-cclass-trib-modal__col-codigo">Código</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->cclassTribRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                wire:key="cclass-row-<?php echo e($row['codigo']); ?>"
                                wire:click="selectCclassTribRow('<?php echo e($row['codigo']); ?>')"
                                wire:dblclick="applyCclassTribRow('<?php echo e($row['codigo']); ?>')"
                                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    'erp-cclass-trib-modal__row',
                                    'is-selected' => $selectedCodigo === $row['codigo'],
                                ]); ?>"
                            >
                                <td class="erp-cclass-trib-modal__col-cst">
                                    <span class="erp-cclass-trib-modal__badge"><?php echo e($row['cst'] !== '' ? $row['cst'] : '—'); ?></span>
                                </td>
                                <td class="erp-cclass-trib-modal__col-grupo" title="<?php echo e($row['cst_descricao']); ?>"><?php echo e($row['cst_descricao'] !== '' ? $row['cst_descricao'] : '—'); ?></td>
                                <td class="erp-cclass-trib-modal__col-codigo"><?php echo e($row['codigo']); ?></td>
                                <td class="erp-cclass-trib-modal__col-desc" title="<?php echo e($row['descricao']); ?>"><?php echo e($row['descricao'] !== '' ? $row['descricao'] : '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="erp-cclass-trib-modal__empty">
                                    Nenhuma classificação encontrada.
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cclassAllowImport): ?>
                                        Atualize a tabela em Empresa → Imposto Padrão.
                                    <?php else: ?>
                                        A tabela padrão do sistema está vazia ou o filtro não retornou resultados.
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="erp-cclass-trib-modal__footer">
                <div class="erp-cclass-trib-modal__selection">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRow): ?>
                        <span class="erp-cclass-trib-modal__selection-label">Selecionado</span>
                        <strong><?php echo e($selectedRow['codigo']); ?></strong>
                        <span class="erp-cclass-trib-modal__selection-desc" title="<?php echo e($selectedRow['descricao']); ?>">
                            <?php echo e($selectedRow['descricao'] !== '' ? $selectedRow['descricao'] : '—'); ?>

                        </span>
                    <?php else: ?>
                        <span class="erp-cclass-trib-modal__selection-hint">Clique em uma linha · duplo clique ou Enter para aplicar</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="erp-cclass-trib-modal__actions">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cclassAllowImport): ?>
                        <label class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'erp-cclass-trib-modal__btn erp-cclass-trib-modal__btn--ghost',
                            'is-busy' => $this->cclassTribImporting,
                        ]); ?>">
                            <input
                                type="file"
                                wire:model="cclassTribUpload"
                                accept=".csv,.txt,.tsv,text/csv,text/plain"
                                class="erp-cclass-trib-modal__file"
                                <?php if($this->cclassTribImporting): echo 'disabled'; endif; ?>
                            >
                            <span wire:loading.remove wire:target="cclassTribUpload">
                                <?php echo e($this->cclassTribImporting ? 'Importando…' : 'Atualizar tabela'); ?>

                            </span>
                            <span wire:loading wire:target="cclassTribUpload">Enviando…</span>
                        </label>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <button
                        type="button"
                        class="erp-cclass-trib-modal__btn erp-cclass-trib-modal__btn--secondary"
                        wire:click="closeCclassTribModal"
                    >Cancelar</button>

                    <button
                        type="button"
                        class="erp-cclass-trib-modal__btn erp-cclass-trib-modal__btn--primary"
                        wire:click="applyCclassTribRow('<?php echo e($selectedCodigo); ?>')"
                        <?php if(! filled($selectedCodigo) || $this->cclassTribImporting): echo 'disabled'; endif; ?>
                    >Aplicar</button>
                </div>
            </footer>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cclassAllowImport): ?>
            <?php echo $__env->make('filament.components.erp.fiscal.cclass-trib-import-progress', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/fiscal/cclass-trib-modal.blade.php ENDPATH**/ ?>