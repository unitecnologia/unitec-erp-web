<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->lookupOpen): ?>
    <?php
        $lookup = $this->lookupViewState;
    ?>

    <div
        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            'erp-lookup-modal',
            'erp-lookup-modal--'.$lookup['type'] => filled($lookup['type'] ?? null),
            'erp-lookup-modal--compact' => in_array($lookup['type'] ?? null, ['grupo', 'marca', 'unidade'], true),
        ]); ?>"
        wire:keydown.escape="handleLookupEscape"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeProductLookup"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-lookup-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-lookup-title"><?php echo e($lookup['title']); ?></span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeProductLookup" title="Fechar">✕</button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lookup['panel'] === 'list'): ?>
                <div class="erp-lookup-modal__body">
                    <fieldset class="erp-lookup-modal__search-box">
                        <legend class="erp-lookup-modal__search-legend">
                            F6 | Localizar &lt;&lt;<?php echo e($lookup['searchLabel']); ?>&gt;&gt;
                        </legend>
                        <input
                            id="erp-lookup-search"
                            type="text"
                            wire:model.live.debounce.200ms="lookupSearch"
                            class="erp-pcad-form__input erp-lookup-modal__search-input"
                        >
                    </fieldset>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($lookup['columns']) > 1): ?>
                        <p class="erp-lookup-modal__hint">
                            Clique no título da coluna para mudar o campo da pesquisa.
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="erp-lookup-modal__grid-wrap">
                        <table class="erp-lookup-modal__grid">
                            <thead>
                                <tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $lookup['columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $columnKey => $columnLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $canSearchColumn = in_array($columnKey, $lookup['searchColumns'] ?? array_keys($lookup['columns']), true);
                                        ?>
                                        <th
                                            scope="col"
                                            <?php if($canSearchColumn): ?>
                                                wire:click="setLookupSearchColumn('<?php echo e($columnKey); ?>')"
                                            <?php endif; ?>
                                            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                'erp-lookup-modal__grid-head',
                                                'erp-lookup-modal__grid-head--active' => $canSearchColumn && $lookup['searchColumn'] === $columnKey,
                                                'erp-lookup-modal__grid-head--static' => ! $canSearchColumn,
                                            ]); ?>"
                                        >
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSearchColumn && $lookup['searchColumn'] === $columnKey): ?>
                                                &gt;&gt;<?php echo e($columnLabel); ?>

                                            <?php else: ?>
                                                <?php echo e($columnLabel); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </th>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $lookup['records']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr
                                        wire:key="lookup-row-<?php echo e($record['id']); ?>"
                                        data-record-id="<?php echo e($record['id']); ?>"
                                        wire:click="highlightLookupRecord(<?php echo e($record['id']); ?>)"
                                        wire:dblclick="confirmProductLookup(<?php echo e($record['id']); ?>)"
                                        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                            'erp-lookup-modal__row',
                                            'erp-lookup-modal__row--selected' => $lookup['highlightedId'] === $record['id'],
                                        ]); ?>"
                                    >
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $lookup['columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $columnKey => $columnLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $isBoolean = in_array($columnKey, $lookup['booleanFields'] ?? [], true);
                                                $flagOn = $isBoolean && ! empty($record['values'][$columnKey]);
                                            ?>
                                            <td
                                                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                                    'erp-lookup-modal__cell--flag' => $isBoolean,
                                                ]); ?>"
                                            >
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBoolean): ?>
                                                    <button
                                                        type="button"
                                                        class="erp-lookup-modal__flag <?php if($flagOn): ?> is-on <?php endif; ?>"
                                                        wire:click.stop="toggleLookupBoolean(<?php echo e($record['id']); ?>, '<?php echo e($columnKey); ?>')"
                                                        title="<?php echo e($flagOn ? 'Visível no app força de vendas' : 'Oculto no app força de vendas'); ?>"
                                                        aria-pressed="<?php echo e($flagOn ? 'true' : 'false'); ?>"
                                                        aria-label="<?php echo e($columnLabel); ?>"
                                                    ></button>
                                                <?php else: ?>
                                                    <?php echo e($record['values'][$columnKey] ?? ''); ?>

                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="<?php echo e(count($lookup['columns'])); ?>" class="erp-lookup-modal__empty">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lookup['type'] === 'ncm'): ?>
                                                Nenhum NCM encontrado.
                                                <div class="erp-lookup-modal__empty-actions">
                                                    <button
                                                        type="button"
                                                        class="erp-pcad-form__btn"
                                                        wire:click="startLookupCreate"
                                                    >
                                                        Cadastrar NCM
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                Nenhum registro encontrado.
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="startLookupCreate" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon">+</span>
                        <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Novo</span>
                    </button>
                    <button type="button" wire:click="startLookupEdit" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon">✎</span>
                        <span class="erp-pcad-actions__label"><kbd>F3</kbd> | Alterar</span>
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($lookup['type'] ?? null) !== 'grupo'): ?>
                        <button type="button" wire:click="modulePending('Imprimir')" class="erp-pcad-actions__btn erp-lookup-modal__btn--disabled" title="Em implementação">
                            <span class="erp-pcad-actions__icon">🖨</span>
                            <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Imprimir</span>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button type="button" wire:click="closeProductLookup" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="erp-lookup-modal__body erp-lookup-modal__body--form">
                    <fieldset class="erp-lookup-modal__form-box">
                        <legend class="erp-lookup-modal__form-legend">
                            <?php echo e($lookup['editing'] ? 'Alterar' : 'Novo'); ?> — <?php echo e($lookup['title']); ?>

                        </legend>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $lookup['formFields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fieldKey => $fieldLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($fieldKey, $lookup['booleanFields'] ?? [], true)): ?>
                                <label class="erp-lookup-modal__form-check" for="erp-lookup-field-<?php echo e($fieldKey); ?>">
                                    <input
                                        id="erp-lookup-field-<?php echo e($fieldKey); ?>"
                                        type="checkbox"
                                        wire:model.boolean="lookupForm.<?php echo e($fieldKey); ?>"
                                        class="erp-lookup-modal__form-check-input"
                                    >
                                    <span><?php echo e($fieldLabel); ?> — mostrar no app força de vendas</span>
                                </label>
                            <?php else: ?>
                                <label class="erp-lookup-modal__form-field" for="erp-lookup-field-<?php echo e($fieldKey); ?>">
                                    <span><?php echo e($fieldLabel); ?></span>
                                    <input
                                        id="erp-lookup-field-<?php echo e($fieldKey); ?>"
                                        type="text"
                                        wire:model="lookupForm.<?php echo e($fieldKey); ?>"
                                        class="erp-pcad-form__input"
                                    >
                                </label>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </fieldset>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions erp-lookup-modal__actions--form">
                    <button type="button" wire:click="saveLookupRecord" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Salvar</span>
                    </button>
                    <button type="button" wire:click="cancelLookupForm" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">↩</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Voltar</span>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/lookup-modal.blade.php ENDPATH**/ ?>