@php
    use App\Models\Empresa;

    $masksJsPath = public_path('js/erp-masks.js');
    $masksJsVersion = file_exists($masksJsPath) ? filemtime($masksJsPath) : time();
    $formJsPath = public_path('js/erp-empresas-form.js');
    $formJsVersion = file_exists($formJsPath) ? filemtime($formJsPath) : time();

    $formTabs = [
        'dados' => 'Dados Básico',
        'parametros' => 'Parâmetros',
        'obs_fisco' => 'Observação Fisco',
        'obs_carne' => 'Observação Carne',
        'obs_nfce' => 'Observações NFC-e',
        'msg_cobranca' => 'Mensagem de Cobrança (WhatsApp)',
    ];
@endphp

<div class="erp-pcad erp-empresas-pcad">
    <div class="erp-pcad__tabs">
        @foreach ($formTabs as $value => $label)
            <button
                type="button"
                wire:click="setActiveFormTab('{{ $value }}')"
                @class([
                    'erp-pcad__tab',
                    'erp-pcad__tab--active' => $this->activeFormTab === $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div @class([
        'erp-pcad__workspace',
        'erp-pcad__workspace--parametros' => $this->activeFormTab === 'parametros',
    ])>
        <div class="erp-pcad__content">
            @if ($this->activeFormTab === 'dados')
                @include('filament.components.erp.empresas.form.tabs.dados-basicos')
            @elseif ($this->activeFormTab === 'parametros')
                @include('filament.components.erp.empresas.form.tabs.parametros')
            @elseif ($this->activeFormTab === 'obs_fisco')
                @include('filament.components.erp.empresas.form.tabs.obs-fisco')
            @elseif ($this->activeFormTab === 'obs_carne')
                @include('filament.components.erp.empresas.form.tabs.obs-carne')
            @elseif ($this->activeFormTab === 'obs_nfce')
                @include('filament.components.erp.empresas.form.tabs.obs-nfce')
            @elseif ($this->activeFormTab === 'msg_cobranca')
                @include('filament.components.erp.empresas.form.tabs.msg-cobranca')
            @endif
        </div>
    </div>
</div>

@include('filament.components.erp.empresas.form.zerar-estoque-negativo-modal')

@include('filament.components.erp.form-scripts')
<script src="{{ asset('js/erp-empresas-form.js') }}?v={{ $formJsVersion }}" defer></script>
