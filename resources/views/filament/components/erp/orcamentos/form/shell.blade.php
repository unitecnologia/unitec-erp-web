@php
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    $readOnly = $this->orcamentoReadOnly();
@endphp

<div class="erp-orc-pcad">
    @include('filament.components.erp.orcamentos.form.header', ['ufs' => $ufs, 'readOnly' => $readOnly])

    <div class="erp-pcad__tabs">
        <button
            type="button"
            wire:click="setActiveFormTab('itens')"
            @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->activeFormTab === 'itens'])
        >Itens</button>
        <button
            type="button"
            wire:click="setActiveFormTab('observacoes')"
            @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->activeFormTab === 'observacoes'])
        >Observações</button>
    </div>

    <div class="erp-pcad__workspace">
        <div class="erp-pcad__content">
            @if ($this->activeFormTab === 'itens')
                @include('filament.components.erp.orcamentos.form.tabs.itens', ['readOnly' => $readOnly])
            @else
                @include('filament.components.erp.orcamentos.form.tabs.observacoes', ['readOnly' => $readOnly])
            @endif
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
@php
    $jsPath = public_path('js/erp-orcamentos-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
@endphp
<script src="{{ asset('js/erp-orcamentos-form.js') }}?v={{ $jsVersion }}" defer></script>
