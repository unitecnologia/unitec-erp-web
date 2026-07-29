<?php
    use App\Models\Person;

    $masksJsPath = public_path('js/erp-masks.js');
    $masksJsVersion = file_exists($masksJsPath) ? filemtime($masksJsPath) : time();
    $jsPath = public_path('js/erp-pessoas-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();

    $formTabs = [
        'dados' => 'Dados Básicos',
        'adicionais' => 'Adicionais',
        'contatos' => 'Contatos',
        'foto' => 'Foto',
    ];

    $parametros = [
        'is_cliente' => 'Clientes',
        'is_fornecedor' => 'Fornecedores',
        'is_funcionario' => 'Funcionários',
        'is_atendente' => 'Atendente',
        'is_tecnico' => 'Técnico',
        'is_administradora' => 'Administradoras',
        'is_parceiro' => 'Parceiros',
        'is_fabricante' => 'Fabricantes',
        'is_transportadora' => 'Transportadoras',
        'is_ccf_spc' => 'CCF/SPC',
        'ativo' => 'Ativo',
    ];
?>

<div class="erp-pcad">
    <div class="erp-pcad__tabs">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $formTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button
                type="button"
                wire:click="setActiveFormTab('<?php echo e($value); ?>')"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->activeFormTab === $value]); ?>"
            ><?php echo e($label); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
        'erp-pcad__workspace',
        'erp-pcad__workspace--dados' => $this->activeFormTab === 'dados',
    ]); ?>">
        <div class="erp-pcad__content">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeFormTab === 'dados'): ?>
                <?php echo $__env->make('filament.components.erp.pessoas.form.tabs.dados-basicos', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php elseif($this->activeFormTab === 'adicionais'): ?>
                <?php echo $__env->make('filament.components.erp.pessoas.form.tabs.adicionais', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php elseif($this->activeFormTab === 'contatos'): ?>
                <?php echo $__env->make('filament.components.erp.pessoas.form.tabs.contatos', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php elseif($this->activeFormTab === 'foto'): ?>
                <?php echo $__env->make('filament.components.erp.pessoas.form.tabs.foto', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeFormTab === 'dados'): ?>
            <fieldset class="erp-pcad__group erp-pessoas-params">
                <legend class="erp-pcad__group-title">Parâmetros</legend>
                <div class="erp-pcad__checks">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $parametros; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model="data.<?php echo e($field); ?>">
                            <span><?php echo e($label); ?></span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>

<?php echo $__env->make('filament.components.erp.form-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset('js/erp-pessoas-form.js')); ?>?v=<?php echo e($jsVersion); ?>" defer></script>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/form/shell.blade.php ENDPATH**/ ?>