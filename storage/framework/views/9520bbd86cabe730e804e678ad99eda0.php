<?php
    use App\Support\Erp\ErpContext;

    $jsPath = public_path('js/erp-produtos-form.js');
    $precifEnterPath = public_path('js/erp-precif-enter-v5.js');
    $jsVersion = max(
        file_exists($jsPath) ? (int) filemtime($jsPath) : time(),
        file_exists($precifEnterPath) ? (int) filemtime($precifEnterPath) : 0,
    );
    $masksJsPath = public_path('js/erp-masks.js');
    $masksJsVersion = file_exists($masksJsPath) ? filemtime($masksJsPath) : time();

    $status = ErpContext::statusBar();
    $empresaId = (int) (session('erp_empresa_id') ?? auth()->user()?->empresa_id ?? 0);
    $empresas = $this->productFormEmpresas();
    $podeTrocarEmpresa = $empresas->count() > 1;

    $dataCadastro = isset($this->record?->created_at)
        ? $this->record->created_at->format('d/m/Y')
        : '';

    $parametros = [
        ['field' => 'ativo', 'label' => 'Ativo', 'disabled' => false],
        ['field' => 'is_fiscal', 'label' => 'É Fiscal', 'disabled' => false],
        ['field' => 'tributacao_monofasica', 'label' => 'Tributação Monofásica', 'disabled' => false],
        ['field' => 'paga_comissao', 'label' => 'Paga Comissão', 'disabled' => false],
        ['field' => 'preco_variavel', 'label' => 'Preço Variavel', 'disabled' => false],
        ['field' => 'is_composicao', 'label' => 'Composição', 'disabled' => ! $this->isEditingProduct()],
        ['field' => 'is_servico', 'label' => 'Serviço', 'disabled' => false],
        ['field' => 'is_grade', 'label' => 'Grade', 'disabled' => ! $this->isEditingProduct()],
        ['field' => 'usa_tab_preco', 'label' => 'Usar Tab. Preço', 'disabled' => false],
        ['field' => 'is_combustivel', 'label' => 'Combustível', 'disabled' => false],
        ['field' => 'usa_imei', 'label' => 'Usa IMEI', 'disabled' => false],
        ['field' => 'contr_est_grade', 'label' => 'Contr. Est. Grade', 'disabled' => false],
        ['field' => 'mostrar_no_app', 'label' => 'Mostrar no App', 'disabled' => false],
        ['field' => 'produto_pesado', 'label' => 'Produto Pesado', 'disabled' => false],
    ];
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->embedsInPdv): ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.shell-pdv', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
<div class="erp-pcad erp-produtos-pcad">
    <div class="erp-produtos-pcad__top">
        <fieldset class="erp-produtos-pcad__empresa-box">
            <legend class="erp-produtos-pcad__fieldset-legend">Selecione empresa</legend>
            <select
                class="erp-pcad-form__select erp-produtos-pcad__empresa-select"
                <?php if(! $podeTrocarEmpresa): echo 'disabled'; endif; ?>
                wire:change="switchProductFormEmpresa($event.target.value)"
            >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $empresas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $empresa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <option value="<?php echo e($empresa->id); ?>" <?php if($empresaId === (int) $empresa->id): echo 'selected'; endif; ?>>
                        <?php echo e($empresa->nome); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <option><?php echo e($status['Empresa'] ?? '—'); ?></option>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </fieldset>

        <div class="erp-produtos-pcad__cadastro-em">
            <label class="erp-produtos-pcad__cadastro-label" for="pprod-data-cadastro">Este produto foi cadastrado em</label>
            <input
                id="pprod-data-cadastro"
                type="text"
                value="<?php echo e($dataCadastro); ?>"
                readonly
                class="erp-pcad-form__input erp-produtos-pcad__cadastro-input"
            >
        </div>

        <div class="erp-produtos-pcad__brand" aria-hidden="true">
            <span class="erp-produtos-pcad__brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="M3.3 7.7 12 12.5l8.7-4.8M12 22V12.5"/>
                </svg>
            </span>
        </div>
    </div>

    <div class="erp-produtos-pcad__workspace">
        <div class="erp-produtos-pcad__fields erp-produtos-pcad__panel">
            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.dados-basicos', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <div class="erp-produtos-pcad__lower">
            <div class="erp-produtos-pcad__tabs-col">
                <div class="erp-produtos-pcad__tabs-area erp-produtos-pcad__panel erp-produtos-pcad__panel--tabs">
                    <div class="erp-produtos-pcad__bottom-tabs">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->visibleProductFormTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button
                                type="button"
                                wire:click="setActiveFormTab('<?php echo e($tab['key']); ?>')"
                                class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-produtos-pcad__bottom-tab', 'erp-produtos-pcad__bottom-tab--active' => $this->activeFormTab === $tab['key']]); ?>"
                            ><?php echo e($tab['label']); ?></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="erp-produtos-pcad__bottom-panel">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeFormTab === 'impostos'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.impostos', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'estoques'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.estoques', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'promocao'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.promocao', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'adicionais'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.adicionais', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'combustivel'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.combustivel', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'balanca'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.balanca', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'grade'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.grade', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'imei'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.imei', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'composicao'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.composicao', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'tabela_preco'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.tabela-preco', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif($this->activeFormTab === 'ultimos_precos'): ?>
                            <?php echo $__env->make('filament.components.erp.produtos.form.tabs.ultimos-precos', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php else: ?>
                            <p class="erp-produtos-pcad__panel-hint">Conteúdo da aba <?php echo e(str_replace('_', ' ', $this->activeFormTab)); ?> em implementação.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="erp-produtos-pcad__foto-col">
                <div class="erp-produtos-pcad__panel erp-produtos-pcad__panel--foto">
                    <?php echo $__env->make('filament.components.erp.produtos.form.product-foto', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            </div>

            <aside class="erp-produtos-pcad__aside">
                <fieldset class="erp-pcad__group erp-produtos-pcad__params erp-produtos-pcad__panel erp-produtos-pcad__panel--params">
                    <legend class="erp-pcad__group-title">Parâmetros</legend>
                    <div class="erp-pcad__checks">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $parametros; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $param): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-pcad__check', 'erp-pcad__check--disabled' => $param['disabled']]); ?>">
                                <input
                                    type="checkbox"
                                    wire:model.live="data.<?php echo e($param['field']); ?>"
                                    <?php if($param['disabled']): echo 'disabled'; endif; ?>
                                >
                                <span><?php echo e($param['label']); ?></span>
                            </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </fieldset>
            </aside>
        </div>
    </div>

    <?php echo $__env->make('filament.components.erp.produtos.form.lookup-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.ncm-confirm-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.duplicate-confirm-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.exit-confirm-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.precificacao-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.produtos.form.replica-precos-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('filament.components.erp.fiscal.cclass-trib-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo $__env->make('filament.components.erp.form-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php
    $cclassImportJsPath = public_path('js/erp-cclass-trib-import.js');
    $cclassImportJsVersion = file_exists($cclassImportJsPath) ? filemtime($cclassImportJsPath) : time();
?>
<script src="<?php echo e(asset('js/erp-cclass-trib-import.js')); ?>?v=<?php echo e($cclassImportJsVersion); ?>" defer></script>
<script src="<?php echo e(asset('js/erp-precif-enter-v5.js')); ?>?v=<?php echo e($jsVersion); ?>"></script>
<script src="<?php echo e(asset('js/erp-produtos-form.js')); ?>?v=<?php echo e($jsVersion); ?>" defer></script>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/shell.blade.php ENDPATH**/ ?>