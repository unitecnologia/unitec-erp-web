<?php
    use App\Models\Person;
?>

<div class="erp-pcad-form erp-pessoas-panel">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-codigo">Código</label>
        <input id="pcad-codigo" type="text" wire:model="data.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-pessoa">Pessoa</label>
        <select id="pcad-pessoa" wire:model.live="data.pessoa_tipo" class="erp-pcad-form__select erp-pcad-form__select--sm">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = Person::pessoaTipos(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cpf">CPF/CNPJ</label>
        <input id="pcad-cpf" type="text" wire:model="data.cpf_cnpj" data-mask="cpf-cnpj" class="erp-pcad-form__input erp-pcad-form__input--doc">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['data.cpf_cnpj'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="erp-pcad-form__error"><?php echo e($message); ?></span>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <button
            type="button"
            data-erp-search-pj
            wire:loading.attr="disabled"
            wire:target="searchPessoaJuridica"
            class="erp-pcad-form__btn"
        >
            <span class="erp-pcad-form__btn-icon">🔍</span>
            <span wire:loading.remove wire:target="searchPessoaJuridica">Pesquisar Pessoa Jurídica</span>
            <span wire:loading wire:target="searchPessoaJuridica">Consultando...</span>
        </button>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-rg">RG/IE</label>
        <input id="pcad-rg" type="text" wire:model="data.rg_ie" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-nome">Nome</label>
        <input id="pcad-nome" type="text" wire:model="data.nome_razao" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-apelido">Apelido</label>
        <input id="pcad-apelido" type="text" wire:model="data.apelido_fantasia" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cep">CEP</label>
        <input id="pcad-cep" type="text" wire:model="data.cep" data-mask="cep" class="erp-pcad-form__input erp-pcad-form__input--cep">
        <button type="button" wire:click="modulePending('Pesquisar CEP')" class="erp-pcad-form__btn">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar CEP
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-endereco">Endereço</label>
        <input id="pcad-endereco" type="text" wire:model="data.endereco" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-numero">Número</label>
        <input id="pcad-numero" type="text" wire:model="data.numero" class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-complemento">Complemento</label>
        <input id="pcad-complemento" type="text" wire:model="data.complemento" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-bairro">Bairro</label>
        <input id="pcad-bairro" type="text" wire:model="data.bairro" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cidade-cod">Cidade</label>
        <input id="pcad-cidade-cod" type="text" wire:model="data.cidade_codigo" class="erp-pcad-form__input erp-pcad-form__input--city-code">
        <input id="pcad-cidade-nome" type="text" wire:model="data.cidade_nome" class="erp-pcad-form__input erp-pcad-form__input--city" list="pcad-cidades">
        <datalist id="pcad-cidades">
            <option value="BALNEÁRIO CAMBORIÚ">
            <option value="FLORIANÓPOLIS">
            <option value="GOIÂNIA">
        </datalist>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-uf">UF</label>
        <select id="pcad-uf" wire:model="data.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = Person::ufs(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-email">Email</label>
        <input id="pcad-email" type="email" wire:model="data.email" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-email2">Email 2</label>
        <input id="pcad-email2" type="email" wire:model="data.email2" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-fone1">Fone 1</label>
        <input id="pcad-fone1" type="text" wire:model="data.fone1" data-mask="phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cel1">Celular 1</label>
        <input id="pcad-cel1" type="text" wire:model="data.celular1" data-mask="mobile-phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-whats">WhatsApp</label>
        <input id="pcad-whats" type="text" wire:model="data.whatsapp" data-mask="mobile-phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-fone2">Fone 2</label>
        <input id="pcad-fone2" type="text" wire:model="data.fone2" data-mask="phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cel2">Celular 2</label>
        <input id="pcad-cel2" type="text" wire:model="data.celular2" data-mask="mobile-phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-regime">Regime Trib.</label>
        <select id="pcad-regime" wire:model="data.regime_tributario" class="erp-pcad-form__select erp-pcad-form__select--md">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = Person::regimesTributarios(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-receb">Tipo de Recebimento</label>
        <select id="pcad-receb" wire:model="data.tipo_recebimento" class="erp-pcad-form__select erp-pcad-form__select--md">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = Person::tiposRecebimento(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cont">Tipo de Cont.</label>
        <select id="pcad-cont" wire:model="data.tipo_contribuinte" class="erp-pcad-form__select erp-pcad-form__select--contrib">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = Person::tiposContribuinte(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/form/tabs/dados-basicos.blade.php ENDPATH**/ ?>