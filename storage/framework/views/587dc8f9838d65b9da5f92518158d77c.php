<?php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'recebi_de' => 'NOMINAL',
    ];

    $pageSizeOptions = [25, 50, 100];
?>

<div
    class="erp-recibos"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if (! $wire.showForm) {
            $event.preventDefault();
            $wire.handleRecibosEscape();
        }
    "
    x-on:erp-focus-recibos-search.window="$el.querySelector('.erp-recibos__input')?.focus()"
>
    <div class="erp-recibos__filter-block">
        <div class="erp-recibos__period">
            <label class="erp-recibos__period-label">
                de
                <input
                    type="date"
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    class="erp-recibos__period-input erp-recibos__period-from"
                >
            </label>
            <label class="erp-recibos__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    class="erp-recibos__period-input"
                >
            </label>
            <button
                type="button"
                wire:click="applyPeriodFilter"
                onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-recibos') ?? document)"
                class="erp-recibos__btn"
            >
                Filtrar Período
            </button>
        </div>

        <div class="erp-recibos__locate-group">
            <span class="erp-recibos__locate-label">F12 | Localizar</span>
            <select wire:model.live="searchColumn" class="erp-recibos__select erp-recibos__locate-field">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $searchFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
            <input
                type="text"
                wire:model="localSearch"
                wire:keydown.enter="search"
                wire:key="recibos-local-search-<?php echo e($this->searchColumn); ?>"
                class="erp-recibos__input erp-recibos__locate-input"
                placeholder="Digite para pesquisar"
                autocomplete="off"
                <?php if($this->searchColumn === 'recebi_de'): ?> data-erp-uppercase <?php endif; ?>
                <?php if($this->searchColumn === 'codigo'): ?> inputmode="numeric" <?php endif; ?>
            >
            <button type="button" wire:click="search" class="erp-recibos__btn">Pesquisa</button>
            <button type="button" wire:click="clearSearch" class="erp-recibos__btn erp-recibos__btn--secondary">Limpar</button>
        </div>

        <div class="erp-recibos__page-size-group">
            <label class="erp-recibos__page-size-label">
                por página
                <select wire:model.live="tableRecordsPerPage" class="erp-recibos__select erp-recibos__page-size-select">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageSizeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </label>
        </div>
    </div>

    <?php echo $__env->make('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('filament.components.erp.form-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/recibos/screen.blade.php ENDPATH**/ ?>