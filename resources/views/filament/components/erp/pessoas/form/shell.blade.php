@php
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
        'is_administradora' => 'Administradoras',
        'is_parceiro' => 'Parceiros',
        'is_fabricante' => 'Fabricantes',
        'is_transportadora' => 'Transportadoras',
        'is_ccf_spc' => 'CCF/SPC',
        'ativo' => 'Ativo',
    ];
@endphp

<div class="erp-pcad">
    <div class="erp-pcad__tabs">
        @foreach ($formTabs as $value => $label)
            <button
                type="button"
                wire:click="setActiveFormTab('{{ $value }}')"
                @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->activeFormTab === $value])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div @class([
        'erp-pcad__workspace',
        'erp-pcad__workspace--dados' => $this->activeFormTab === 'dados',
    ])>
        <div class="erp-pcad__content">
            @if ($this->activeFormTab === 'dados')
                @include('filament.components.erp.pessoas.form.tabs.dados-basicos')
            @elseif ($this->activeFormTab === 'adicionais')
                @include('filament.components.erp.pessoas.form.tabs.adicionais')
            @elseif ($this->activeFormTab === 'contatos')
                @include('filament.components.erp.pessoas.form.tabs.contatos')
            @elseif ($this->activeFormTab === 'foto')
                @include('filament.components.erp.pessoas.form.tabs.foto')
            @endif
        </div>

        @if ($this->activeFormTab === 'dados')
            <fieldset class="erp-pcad__group">
                <legend class="erp-pcad__group-title">Parâmetros</legend>
                <div class="erp-pcad__checks">
                    @foreach ($parametros as $field => $label)
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model="data.{{ $field }}">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </div>
</div>

@include('filament.components.erp.form-scripts')
<script src="{{ asset('js/erp-pessoas-form.js') }}?v={{ $jsVersion }}" defer></script>
