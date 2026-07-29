@php
    $readOnly = $this->osReadOnly();
    $mainTabs = [
        'dados' => 'Dados da OS',
        'equipamento' => 'Equipamento',
        'defeito' => 'Defeito/Problema',
        'observacoes' => 'Observações',
    ];
@endphp

<div class="erp-os-shell">
    <div class="erp-os-shell__tabs" role="tablist">
        @foreach ($mainTabs as $tab => $label)
            <button
                type="button"
                role="tab"
                wire:click="setActiveFormTab('{{ $tab }}')"
                @class(['erp-os-shell__tab', 'erp-os-shell__tab--active' => $this->activeFormTab === $tab])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="erp-os-shell__workspace">
        <div class="erp-os-shell__content">
            @if ($this->activeFormTab === 'dados')
                @include('filament.components.erp.ordens-servico.form.tabs.dados', ['readOnly' => $readOnly])
            @elseif ($this->activeFormTab === 'equipamento')
                @include('filament.components.erp.ordens-servico.form.tabs.equipamento', ['readOnly' => $readOnly])
            @elseif ($this->activeFormTab === 'defeito')
                @include('filament.components.erp.ordens-servico.form.tabs.defeito', ['readOnly' => $readOnly])
            @else
                @include('filament.components.erp.ordens-servico.form.tabs.observacoes', ['readOnly' => $readOnly])
            @endif
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
@php
    $jsPath = public_path('js/erp-os-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
@endphp
<script src="{{ asset('js/erp-os-form.js') }}?v={{ $jsVersion }}" defer></script>
