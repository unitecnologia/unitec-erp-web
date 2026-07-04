@php
    use App\Models\Empresa;
@endphp

<div class="erp-pcad-form erp-empresas-dados-form">
    <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--line1">
        <label class="erp-pcad-form__label" for="emp-codigo">Código</label>
        <input id="emp-codigo" type="text" wire:model="data.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs" readonly tabindex="-1">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-pessoa">Tipo Pessoa *</label>
        <select id="emp-pessoa" wire:model.live="data.pessoa_tipo" class="erp-pcad-form__select erp-pcad-form__select--sm">
            @foreach (Empresa::pessoaTipos() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-cnpj"><u>F2</u> | CPF/CNPJ *</label>
        <input id="emp-cnpj" type="text" wire:model="data.cnpj" data-mask="cpf-cnpj" class="erp-pcad-form__input erp-pcad-form__input--doc">
        @error('data.cnpj')
            <span class="erp-pcad-form__error">{{ $message }}</span>
        @enderror
        <button
            type="button"
            data-erp-search-pj
            wire:loading.attr="disabled"
            wire:target="searchEmpresaCnpj"
            class="erp-pcad-form__btn erp-empresas-dados-form__btn-search"
        >
            <span class="erp-pcad-form__btn-icon">🔍</span>
            <span wire:loading.remove wire:target="searchEmpresaCnpj">Pesquisar CNPJ</span>
            <span wire:loading wire:target="searchEmpresaCnpj">...</span>
        </button>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-ie">RG / IE</label>
        <input id="emp-ie" type="text" wire:model="data.ie" class="erp-pcad-form__input erp-pcad-form__input--sm">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-im">IM</label>
        <input id="emp-im" type="text" wire:model="data.im" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-cnae">CNAE</label>
        <input id="emp-cnae" type="text" wire:model="data.cnae" class="erp-pcad-form__input erp-pcad-form__input--sm">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-regime">Regime Tributário</label>
        <select id="emp-regime" wire:model="data.regime_tributario" class="erp-pcad-form__select erp-pcad-form__select--md">
            @foreach (Empresa::regimesTributarios() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--names">
        <label class="erp-pcad-form__label" for="emp-razao">Nome / Razão Social *</label>
        <input id="emp-razao" type="text" wire:model="data.razao_social" class="erp-pcad-form__input">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-fantasia">Apelido / Nome Fantasia *</label>
        <input id="emp-fantasia" type="text" wire:model="data.fantasia" class="erp-pcad-form__input">
    </div>

    <div class="erp-empresas-dados-form__split">
        <div class="erp-empresas-dados-form__main">
            <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--cep">
                <label class="erp-pcad-form__label" for="emp-cep"><u>F2</u> | CEP *</label>
                <input id="emp-cep" type="text" wire:model="data.cep" data-mask="cep" class="erp-pcad-form__input erp-pcad-form__input--cep">
                <button type="button" wire:click="modulePending('Pesquisar CEP')" class="erp-pcad-form__btn erp-empresas-dados-form__btn-icon-only">
                    <span class="erp-pcad-form__btn-icon">🔍</span>
                </button>
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-endereco">Endereço *</label>
                <input id="emp-endereco" type="text" wire:model="data.endereco" class="erp-pcad-form__input">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-numero">Número *</label>
                <input id="emp-numero" type="text" wire:model="data.numero" class="erp-pcad-form__input erp-pcad-form__input--xs">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-complemento">Complemento</label>
                <input id="emp-complemento" type="text" wire:model="data.complemento" class="erp-pcad-form__input">
            </div>

            <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--local">
                <label class="erp-pcad-form__label" for="emp-bairro">Bairro *</label>
                <input id="emp-bairro" type="text" wire:model="data.bairro" class="erp-pcad-form__input">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-cidade-cod">Código</label>
                <input id="emp-cidade-cod" type="text" wire:model="data.cidade_codigo" class="erp-pcad-form__input erp-pcad-form__input--city-code">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-cidade">Cidade *</label>
                <input id="emp-cidade" type="text" wire:model="data.cidade" class="erp-pcad-form__input" list="emp-cidades">
                <datalist id="emp-cidades">
                    <option value="BALNEÁRIO CAMBORIÚ">
                    <option value="FLORIANÓPOLIS">
                    <option value="ITAJAÍ">
                </datalist>
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-uf">UF *</label>
                <select id="emp-uf" wire:model="data.uf" class="erp-pcad-form__select erp-pcad-form__select--uf">
                    @foreach (Empresa::ufs() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-pais-cod">Código</label>
                <input id="emp-pais-cod" type="text" wire:model="data.pais_codigo" class="erp-pcad-form__input erp-pcad-form__input--city-code">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-pais">País</label>
                <input id="emp-pais" type="text" wire:model="data.pais" class="erp-pcad-form__input">
            </div>

            <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--email">
                <label class="erp-pcad-form__label" for="emp-email">Email</label>
                <input id="emp-email" type="email" wire:model="data.email" class="erp-pcad-form__input">
            </div>

            <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--site">
                <label class="erp-pcad-form__label" for="emp-site">Site</label>
                <input id="emp-site" type="text" wire:model="data.site" class="erp-pcad-form__input">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-telefone">Telefone *</label>
                <input id="emp-telefone" type="text" wire:model="data.telefone" data-mask="phone" class="erp-pcad-form__input">
            </div>

            <div class="erp-pcad-form__row erp-empresas-dados-form__row erp-empresas-dados-form__row--resp">
                <label class="erp-pcad-form__label" for="emp-responsavel">Responsável pela empresa</label>
                <input id="emp-responsavel" type="text" wire:model="data.responsavel" class="erp-pcad-form__input">
                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="emp-cnpj-rep">CNPJ Representante</label>
                <input id="emp-cnpj-rep" type="text" wire:model="data.cnpj_representante" data-mask="cpf-cnpj" data-mask-pessoa="juridica" class="erp-pcad-form__input">
            </div>

            <fieldset class="erp-empresas-pcad__atividade">
                <legend class="erp-empresas-pcad__atividade-title">Escolha o tipo de atividade da sua empresa</legend>
                <div class="erp-empresas-pcad__atividade-grid">
                    @foreach (Empresa::tiposAtividade() as $value => $label)
                        <label class="erp-empresas-pcad__atividade-item">
                            <input type="radio" wire:model="data.tipo_atividade" value="{{ $value }}">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="erp-pcad__check erp-empresas-pcad__ativo">
                <input type="checkbox" wire:model="data.ativo">
                <span>Ativo</span>
            </label>
        </div>

        <aside class="erp-empresas-dados-form__logo">
            @include('filament.components.erp.empresas.form.logo')
        </aside>
    </div>
</div>
