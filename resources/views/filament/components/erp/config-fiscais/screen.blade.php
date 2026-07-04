@php
    $tabs = [
        'webservice' => 'WebService',
        'certificado' => 'Certificado',
        'nfe' => 'NF-e',
        'email' => 'E-mail',
    ];

    $configFiscaisJsPath = public_path('js/erp-config-fiscais.js');
    $configFiscaisJsVersion = file_exists($configFiscaisJsPath) ? filemtime($configFiscaisJsPath) : time();
@endphp

<div class="erp-config-fiscais-window">
    <header class="erp-config-fiscais-window__titlebar">
        <span class="erp-config-fiscais-window__title">Configurações</span>
        <div class="erp-config-fiscais-window__empresa">
            <label for="erp-config-fiscais-empresa">Empresa</label>
            <span id="erp-config-fiscais-empresa">{{ $this->empresaNome }}</span>
        </div>
        <button
            type="button"
            class="erp-config-fiscais-window__close"
            wire:click="closeScreen"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-config-fiscais-window__body">
        <div class="erp-pcad erp-config-fiscais-pcad">
            <div class="erp-pcad__tabs">
                @foreach ($tabs as $value => $label)
                    <button
                        type="button"
                        wire:click="setActiveTab('{{ $value }}')"
                        @class([
                            'erp-pcad__tab',
                            'erp-pcad__tab--active' => $this->activeTab === $value,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>

            <div class="erp-pcad__workspace">
                <div class="erp-pcad__content">
                    @if ($this->activeTab === 'webservice')
                        @include('filament.components.erp.config-fiscais.tabs.webservice')
                    @elseif ($this->activeTab === 'certificado')
                        @include('filament.components.erp.config-fiscais.tabs.certificado')
                    @elseif ($this->activeTab === 'nfe')
                        @include('filament.components.erp.config-fiscais.tabs.nfe')
                    @else
                        @include('filament.components.erp.config-fiscais.tabs.email')
                    @endif
                </div>
            </div>
        </div>

        @include('filament.components.erp.config-fiscais.action-bar')
    </div>
</div>

<script src="{{ asset('js/erp-config-fiscais.js') }}?v={{ $configFiscaisJsVersion }}" defer></script>
