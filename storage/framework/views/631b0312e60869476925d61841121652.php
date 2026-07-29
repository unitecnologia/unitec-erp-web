
<?php
    $pageClass = $pageClass ?? 'erp-unidades';
    $searchFields = $searchFields ?? ['codigo' => 'CÓDIGO'];
    $pageSizeOptions = [25, 50, 100];
    $showFieldDropdown = $showFieldDropdown ?? (count($searchFields) > 1);
    $wireKeyPrefix = $wireKeyPrefix ?? $pageClass;
    $uppercaseColumns = collect(explode(',', (string) ($uppercaseColumns ?? 'nome,descricao,sigla,fantasia,razao_social,q')))
        ->map(fn ($v) => trim($v))
        ->filter()
        ->all();
    // Sempre força caixa alta no Localizar (cadastros).
    $forceSearchUppercase = $forceSearchUppercase ?? true;
    $extraClass = trim((string) ($extraClass ?? ''));
    $hint = $hint ?? null;
    $beforeFiltersView = $beforeFiltersView ?? null;
    $searchColumn = $this->searchColumn ?? array_key_first($searchFields) ?? 'q';
?>

<div class="<?php echo e($pageClass); ?> <?php echo e($extraClass); ?>" wire:ignore.self>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($beforeFiltersView)): ?>
        <?php echo $__env->make($beforeFiltersView, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="<?php echo e($pageClass); ?>__filters">
        <div class="<?php echo e($pageClass); ?>__filters-row">
            <div class="<?php echo e($pageClass); ?>__search-group">
                <span class="<?php echo e($pageClass); ?>__locate-label">F6 | Localizar</span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showFieldDropdown): ?>
                    <?php echo $__env->make('filament.components.erp.shared.search-field-dropdown', [
                        'fields' => $searchFields,
                        'searchColumn' => $searchColumn,
                        'wireProperty' => 'searchColumn',
                    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="<?php echo e($wireKeyPrefix); ?>-local-search-<?php echo e($searchColumn); ?>"
                    class="<?php echo e($pageClass); ?>__input <?php echo e($pageClass); ?>__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    <?php if($forceSearchUppercase || in_array($searchColumn, $uppercaseColumns, true)): ?> data-erp-uppercase <?php endif; ?>
                    <?php if($searchColumn === 'codigo'): ?> inputmode="numeric" <?php endif; ?>
                >
            </div>

            <div class="<?php echo e($pageClass); ?>__search-actions">
                <button type="button" wire:click="search" class="<?php echo e($pageClass); ?>__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="<?php echo e($pageClass); ?>__btn <?php echo e($pageClass); ?>__btn--secondary">Limpar</button>
            </div>

            <div class="<?php echo e($pageClass); ?>__page-size-group">
                <label class="<?php echo e($pageClass); ?>__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="<?php echo e($pageClass); ?>__select <?php echo e($pageClass); ?>__page-size-select">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageSizeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($hint)): ?>
        <p class="<?php echo e($pageClass); ?>__hint"><?php echo e($hint); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php echo $__env->make('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/shared/cadastro-list-screen.blade.php ENDPATH**/ ?>