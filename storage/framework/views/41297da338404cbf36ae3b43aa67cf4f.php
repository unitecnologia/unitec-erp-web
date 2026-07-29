<?php
    $isPeriodoEntrada = $this->searchColumn === 'periodo_entrada';
    $isDateSearch = $this->searchColumn === 'data_emissao';
?>

<div class="erp-nfe__locate erp-nfe__filtro-unificado">
    <span class="erp-nfe__locate-label"><kbd>F12</kbd> Filtro</span>
    <div class="erp-nfe__locate-controls">
        <select wire:model.live="searchColumn" class="erp-nfe__select erp-nfe__search-field">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $filterFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPeriodoEntrada): ?>
            <div class="erp-nfe__search-date-range" wire:key="nf-forn-filter-periodo-entrada">
                <label class="erp-nfe__period-label">
                    de
                    <input
                        type="date"
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        class="erp-nfe__period-input erp-nfe__period-from"
                    >
                </label>
                <label class="erp-nfe__period-label">
                    até
                    <input
                        type="date"
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        class="erp-nfe__period-input erp-nfe__period-to"
                    >
                </label>
            </div>
        <?php elseif($isDateSearch): ?>
            <div class="erp-nfe__search-date-range" wire:key="nf-forn-filter-data-emissao">
                <label class="erp-nfe__period-label">
                    de
                    <input
                        type="date"
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        class="erp-nfe__period-input erp-nfe__search-date-from"
                    >
                </label>
                <label class="erp-nfe__period-label">
                    até
                    <input
                        type="date"
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
                        class="erp-nfe__period-input erp-nfe__search-date-to"
                    >
                </label>
            </div>
        <?php else: ?>
            <input
                type="text"
                wire:model.live="localSearch"
                wire:keydown.enter="applyFilter"
                wire:key="nf-forn-filter-text-<?php echo e($this->searchColumn); ?>"
                class="erp-nfe__input erp-nfe__search-text"
                placeholder="DIGITE AQUI SUA PESQUISA"
                autocomplete="off"
                <?php if($this->searchColumn === 'nome'): ?> data-erp-uppercase <?php endif; ?>
                <?php if(in_array($this->searchColumn, ['chave', 'cnpj'], true)): ?> inputmode="numeric" <?php endif; ?>
                <?php if($this->searchColumn === 'chave'): ?> maxlength="44" <?php endif; ?>
            >
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <button
            type="button"
            wire:click="applyFilter"
            onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-nfe') ?? document)"
            class="erp-nfe__btn erp-nfe__btn--filter"
        >
            Filtrar
        </button>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/toolbar-filters.blade.php ENDPATH**/ ?>