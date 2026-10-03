@php
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    $readOnly = $this->orcamentoReadOnly();
@endphp

<div class="erp-orc-pcad">
    @include('filament.components.erp.orcamentos.form.header', ['readOnly' => $readOnly])

    @include('filament.components.erp.orcamentos.form.produto-bar', ['readOnly' => $readOnly])

    <div class="erp-pcad__workspace">
        <div class="erp-pcad__content">
            @include('filament.components.erp.orcamentos.form.tabs.itens', ['readOnly' => $readOnly])
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
@php
    $jsPath = public_path('js/erp-orcamentos-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
@endphp
<script src="{{ asset('js/erp-orcamentos-form.js') }}?v={{ $jsVersion }}" defer></script>
