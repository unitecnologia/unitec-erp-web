<?php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome_razao' => 'RAZÃO/NOME',
        'apelido_fantasia' => 'FANTASIA/APELIDO',
        'cpf_cnpj' => 'CPF/CNPJ',
        'rg_ie' => 'RG/IE',
        'endereco' => 'ENDEREÇO',
    ];

    $pageSizeOptions = [25, 50, 100];
?>

<div class="erp-pessoas" wire:ignore.self>
    <div class="erp-pessoas__filters">
        <div class="erp-pessoas__filters-row">
            <div class="erp-pessoas__search-group">
                <span class="erp-pessoas__locate-label">F6 | Localizar</span>
                <?php echo $__env->make('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="pessoas-local-search-<?php echo e($this->searchColumn); ?>-<?php echo e($this->tipoFilter); ?>"
                    class="erp-pessoas__input erp-pessoas__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    <?php if(in_array($this->searchColumn, ['nome_razao', 'apelido_fantasia', 'endereco'], true)): ?> data-erp-uppercase <?php endif; ?>
                    <?php if($this->searchColumn === 'codigo'): ?> inputmode="numeric" <?php endif; ?>
                >
            </div>

            <div class="erp-pessoas__search-actions">
                <button type="button" wire:click="search" class="erp-pessoas__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-pessoas__btn erp-pessoas__btn--secondary">Limpar</button>
            </div>

            <div class="erp-pessoas__page-size-group">
                <label class="erp-pessoas__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-pessoas__select erp-pessoas__page-size-select">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageSizeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <?php echo $__env->make('filament.components.erp.pessoas.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <p class="erp-pessoas__hint">
        Clique na tecla [DELETE] para excluir pessoa.
    </p>

    <?php echo $__env->make('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/screen.blade.php ENDPATH**/ ?>