<?php
    $isSeriais = $this->isSeriaisView();

    $searchFields = $isSeriais
        ? [
            'descricao' => 'DESCRIÇÃO',
            'numero_serie' => 'Nº SÉRIE',
        ]
        : [
            'codigo' => 'CÓDIGO',
            'referencia' => 'REFERÊNCIA',
            'codigo_barras' => 'CÓD. BARRAS',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'preco_venda' => 'PREÇO VENDA',
            'estoque' => 'QTD ATUAL',
            'localizacao' => 'LOCALIZAÇÃO',
        ];

    $pageSizeOptions = [25, 50, 100];
?>

<div class="erp-produtos" wire:ignore.self>
    <div class="erp-produtos__filters">
        <div class="erp-produtos__filters-row">
            <div class="erp-produtos__search-group">
                <span class="erp-produtos__locate-label">F6 | Localizar</span>
                <?php echo $__env->make('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="produtos-local-search-<?php echo e($this->searchColumn); ?>-<?php echo e($this->viewFilter); ?>"
                    class="erp-produtos__input erp-produtos__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    <?php if($this->searchColumn === 'codigo'): ?> inputmode="numeric" <?php endif; ?>
                    <?php if(in_array($this->searchColumn, ['preco_venda', 'estoque'], true)): ?> inputmode="decimal" <?php endif; ?>
                >
            </div>

            <div class="erp-produtos__search-actions">
                <button type="button" wire:click="search" class="erp-produtos__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-produtos__btn erp-produtos__btn--secondary">Limpar</button>
            </div>

            <div class="erp-produtos__page-size-group">
                <label class="erp-produtos__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-produtos__select erp-produtos__page-size-select">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pageSizeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <?php echo $__env->make('filament.components.erp.produtos.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <p class="erp-produtos__hint">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSeriais): ?>
            Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.
        <?php else: ?>
            Clique na tecla [DELETE] para excluir Produto.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>

    <?php echo $__env->make('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/screen.blade.php ENDPATH**/ ?>