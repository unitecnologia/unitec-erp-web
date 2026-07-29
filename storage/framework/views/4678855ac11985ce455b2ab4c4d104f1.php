<?php
    use Illuminate\Support\Carbon;

    $searchFields = [
        'cliente' => 'CLIENTE',
        'numero' => 'NÚMERO',
        'vendedor' => 'VENDEDOR',
        'cidade' => 'CIDADE',
        'uf' => 'UF',
    ];

    $pageSizeOptions = [25, 50, 100];
    $periodoDeValor = filled($this->periodoDe)
        ? Carbon::parse($this->periodoDe)->format('d/m/Y')
        : '';
    $periodoAteValor = filled($this->periodoAte)
        ? Carbon::parse($this->periodoAte)->format('d/m/Y')
        : '';
?>

<div class="erp-orcamentos">
    <div class="erp-orcamentos__filters">
        <div class="erp-orcamentos__filters-row">
            <div class="erp-orcamentos__search-group">
                <span class="erp-orcamentos__locate-label">Localizar</span>
                <select wire:model.live="searchColumn" class="erp-orcamentos__select erp-orcamentos__search-field">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $searchFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <span class="erp-orcamentos__search-desc-label">Descrição</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="localSearch"
                    wire:key="orcamentos-local-search-<?php echo e($this->searchColumn); ?>"
                    class="erp-orcamentos__input erp-orcamentos__search-text"
                    placeholder="Digite para pesquisar"
                >
            </div>

            <div
                class="erp-orcamentos__period-group"
                wire:ignore.self
                data-erp-date-group
                data-erp-date-auto-apply="1"
                data-erp-date-apply-method="applyPeriodFilterAuto"
            >
                <span class="erp-orcamentos__filter-title">Filtro</span>
                <label class="erp-orcamentos__period-label">
                    Período de
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="<?php echo e($this->periodoDe); ?>"
                        value="<?php echo e($periodoDeValor); ?>"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-orcamentos__period-input erp-orcamentos__period-from erp-date-input"
                    >
                </label>
                <label class="erp-orcamentos__period-label">
                    até
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="<?php echo e($this->periodoAte); ?>"
                        value="<?php echo e($periodoAteValor); ?>"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-orcamentos__period-input erp-date-input"
                    >
                </label>
            </div>

            <div class="erp-orcamentos__page-size-group">
                <label class="erp-orcamentos__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-orcamentos__select erp-orcamentos__page-size-select">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageSizeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <?php echo $__env->make('filament.components.erp.orcamentos.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('filament.components.erp.form-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php
        use App\Support\Erp\ErpAssetVersion;
    ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/orcamentos/screen.blade.php ENDPATH**/ ?>