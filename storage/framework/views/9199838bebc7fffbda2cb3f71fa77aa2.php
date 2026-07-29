<?php
    $canUpdate = \App\Support\Erp\ErpAccess::currentCan('ajusta_preco.update');
    $selCount = count($this->selecionados);
?>

<div
    class="erp-ajusta-precos"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        $event.preventDefault();
        $wire.handleAjustaPrecosEscape();
    "
>
    <div class="erp-ajusta-precos__filters">
        <div class="erp-ajusta-precos__filters-toolbar">
            <button
                type="button"
                wire:click="pesquisar"
                class="erp-ajusta-precos__btn erp-ajusta-precos__btn--search"
                data-erp-key="F5"
                title="Efetuar consulta (F5)"
            >
                <svg class="erp-ajusta-precos__btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="8.5" cy="8.5" r="5.25" stroke="currentColor" stroke-width="1.75"/>
                    <path d="M12.5 12.5L16.25 16.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
                <span class="erp-ajusta-precos__btn-label"><kbd>F5</kbd> Consulta</span>
            </button>

            <button
                type="button"
                wire:click="marcarOuInverterTodos"
                class="erp-ajusta-precos__btn erp-ajusta-precos__btn--ghost"
                data-erp-key="F2"
                title="Marcar todos / inverter (F2)"
            >
                <span class="erp-ajusta-precos__btn-label"><kbd>F2</kbd> Marcar / Inverter</span>
            </button>

            <button
                type="button"
                wire:click="limparFiltros"
                class="erp-ajusta-precos__btn erp-ajusta-precos__btn--ghost"
                title="Limpar filtros"
            >
                Limpar
            </button>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selCount > 0): ?>
                <span class="erp-ajusta-precos__sel-badge"><?php echo e($selCount); ?> selecionado(s)</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="erp-ajusta-precos__filters-row">
            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--desc">
                <span class="erp-ajusta-precos__label">Descrição</span>
                <div class="erp-ajusta-precos__combo">
                    <select wire:model="descricaoOp" class="erp-ajusta-precos__select erp-ajusta-precos__select--op">
                        <option value="contem">Contém</option>
                        <option value="igual">Igual</option>
                        <option value="inicia">Inicia</option>
                    </select>
                    <input
                        type="text"
                        wire:model="descricaoFilter"
                        wire:keydown.enter="pesquisar"
                        class="erp-ajusta-precos__input erp-ajusta-precos__input--descricao"
                        placeholder="Buscar descrição…"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--barra">
                <span class="erp-ajusta-precos__label">Cód. Barras / Código</span>
                <input
                    type="text"
                    wire:model="codigoBarrasFilter"
                    wire:keydown.enter="pesquisar"
                    class="erp-ajusta-precos__input"
                    placeholder="Código ou barras"
                    autocomplete="off"
                >
            </label>

            <div class="erp-ajusta-precos__seg-group" role="group" aria-label="Status">
                <span class="erp-ajusta-precos__label">Status</span>
                <div class="erp-ajusta-precos__seg" role="radiogroup">
                    <label class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-ajusta-precos__chip', 'is-active' => $this->statusFilter === 'ativo']); ?>">
                        <input type="radio" wire:model="statusFilter" value="ativo">
                        <span>Ativo</span>
                    </label>
                    <label class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-ajusta-precos__chip', 'is-active' => $this->statusFilter === 'inativo']); ?>">
                        <input type="radio" wire:model="statusFilter" value="inativo">
                        <span>Inativo</span>
                    </label>
                    <label class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-ajusta-precos__chip', 'is-active' => $this->statusFilter === 'todos']); ?>">
                        <input type="radio" wire:model="statusFilter" value="todos">
                        <span>Todos</span>
                    </label>
                </div>
            </div>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--impl-check" title="Não suportado no web">
                <input type="checkbox" class="erp-ajusta-precos__check-impl" disabled>
                <span class="erp-ajusta-precos__label">
                    Todas Empresas
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
            </label>
        </div>

        
        <div class="erp-ajusta-precos__filters-row">
            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--mid">
                <span class="erp-ajusta-precos__label">Marca</span>
                <select wire:model="marcaFilter" class="erp-ajusta-precos__select">
                    <option value="todos">&lt;todas&gt;</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->marcasOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nome => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($nome); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--mid">
                <span class="erp-ajusta-precos__label">Grupo</span>
                <select wire:model="grupoFilter" class="erp-ajusta-precos__select">
                    <option value="todos">&lt;todos&gt;</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->gruposOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nome => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($nome); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--mid">
                <span class="erp-ajusta-precos__label">
                    Seção
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
                <select class="erp-ajusta-precos__select erp-ajusta-precos__select--impl" disabled>
                    <option>&lt;todas&gt;</option>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--mid">
                <span class="erp-ajusta-precos__label">
                    SubGrupo
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
                <select class="erp-ajusta-precos__select erp-ajusta-precos__select--impl" disabled>
                    <option>&lt;todos&gt;</option>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--mid">
                <span class="erp-ajusta-precos__label">
                    Fabricante
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
                <select class="erp-ajusta-precos__select erp-ajusta-precos__select--impl" disabled>
                    <option>&lt;todos&gt;</option>
                </select>
            </label>
        </div>

        
        <div class="erp-ajusta-precos__filters-row">
            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--forn">
                <span class="erp-ajusta-precos__label">Fornecedor</span>
                <select wire:model="fornecedorFilter" class="erp-ajusta-precos__select">
                    <option value="todos">&lt;todos&gt;</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->fornecedoresOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $nome): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($id); ?>"><?php echo e($nome); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--ncm">
                <span class="erp-ajusta-precos__label">NCM</span>
                <input
                    type="text"
                    wire:model="ncmFilter"
                    wire:keydown.enter="pesquisar"
                    class="erp-ajusta-precos__input"
                    placeholder="NCM"
                    maxlength="10"
                    autocomplete="off"
                >
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--wide">
                <span class="erp-ajusta-precos__label">
                    Agrupamento de Preço
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
                <select class="erp-ajusta-precos__select erp-ajusta-precos__select--impl" disabled>
                    <option>&lt;todos&gt;</option>
                </select>
            </label>

            <label class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--wide">
                <span class="erp-ajusta-precos__label">
                    Segmentação
                    <span class="erp-ajusta-precos__impl-badge">Implementando</span>
                </span>
                <select class="erp-ajusta-precos__select erp-ajusta-precos__select--impl" disabled>
                    <option>&lt;todas&gt;</option>
                </select>
            </label>
        </div>

        
        <div class="erp-ajusta-precos__discount-row" aria-label="Índices e descontos (em implementação)">
            <div class="erp-ajusta-precos__discount-title">
                % Máximo / % Índice Desconto
                <span class="erp-ajusta-precos__impl-badge">Implementando</span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                '% Máx. Var.',
                '% Máx. Atac.',
                '% Máx. Esp.',
                '% Ídx. Var.',
                '% Ídx. Atac.',
                '% Ídx. Esp.',
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $implLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label
                    class="erp-ajusta-precos__field erp-ajusta-precos__field--impl erp-ajusta-precos__field--narrow"
                    title="<?php echo e([
                        '% Máximo Desconto Varejo',
                        '% Máximo Desconto Atacado',
                        '% Máximo Desconto Especial',
                        '% Índice Desconto Varejo',
                        '% Índice Desconto Atacado',
                        '% Índice Desconto Especial',
                    ][$i]); ?> (em implementação)"
                >
                    <span class="erp-ajusta-precos__label"><?php echo e($implLabel); ?></span>
                    <input
                        type="text"
                        class="erp-ajusta-precos__input erp-ajusta-precos__input--impl"
                        value=""
                        disabled
                        readonly
                        placeholder="—"
                        tabindex="-1"
                    >
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUpdate): ?>
            <div class="erp-ajusta-precos__apply">
                <div class="erp-ajusta-precos__apply-title">Aplicar aos selecionados</div>
                <div class="erp-ajusta-precos__apply-row">
                    <?php
                        $applyFields = [
                            ['key' => 'preco_compra', 'label' => 'Pr. Fornecedor', 'model' => 'applyPrecoCompra', 'inputmode' => 'decimal'],
                            ['key' => 'preco_custo', 'label' => 'Custo', 'model' => 'applyPrecoCusto', 'inputmode' => 'decimal'],
                            ['key' => 'pct_lucro', 'label' => '% Margem Var.', 'model' => 'applyPctLucro', 'inputmode' => 'decimal'],
                            ['key' => 'preco_venda', 'label' => 'Preço Varejo', 'model' => 'applyPrecoVenda', 'inputmode' => 'decimal'],
                            ['key' => 'preco_atacado', 'label' => 'Preço Atacado', 'model' => 'applyPrecoAtacado', 'inputmode' => 'decimal'],
                            ['key' => 'preco_especial', 'label' => 'Preço Especial', 'model' => 'applyPrecoEspecial', 'inputmode' => 'decimal'],
                            ['key' => 'origem', 'label' => 'Origem', 'model' => 'applyOrigem', 'inputmode' => 'numeric', 'maxlength' => 1],
                            ['key' => 'csosn', 'label' => 'CSOSN', 'model' => 'applyCsosn', 'inputmode' => 'numeric', 'maxlength' => 3],
                            ['key' => 'cst_icms', 'label' => 'CST', 'model' => 'applyCst', 'inputmode' => 'numeric', 'maxlength' => 3],
                            ['key' => 'aliq_icms', 'label' => '% ICMS', 'model' => 'applyAliqIcms', 'inputmode' => 'decimal'],
                        ];
                    ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $applyFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="erp-ajusta-precos__apply-field">
                            <span class="erp-ajusta-precos__label"><?php echo e($field['label']); ?></span>
                            <div class="erp-ajusta-precos__apply-control">
                                <input
                                    type="text"
                                    inputmode="<?php echo e($field['inputmode']); ?>"
                                    <?php if(! empty($field['maxlength'])): ?> maxlength="<?php echo e($field['maxlength']); ?>" <?php endif; ?>
                                    wire:model="<?php echo e($field['model']); ?>"
                                    class="erp-ajusta-precos__input erp-ajusta-precos__input--apply"
                                    autocomplete="off"
                                >
                                <button
                                    type="button"
                                    class="erp-ajusta-precos__apply-btn"
                                    wire:click="aplicarCampoSelecionados(<?php echo e(\Illuminate\Support\Js::from($field['key'])); ?>)"
                                    title="Aplicar <?php echo e($field['label']); ?> aos selecionados"
                                    aria-label="Aplicar <?php echo e($field['label']); ?>"
                                >
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.6"/>
                                        <path d="M6.2 10.2L8.7 12.7L13.8 7.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="erp-ajusta-precos__section-bar" role="heading" aria-level="2">Dados do produto</div>

    <?php echo $__env->make('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/ajusta-precos/screen.blade.php ENDPATH**/ ?>