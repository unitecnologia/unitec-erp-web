@php
    $regJs = 'js/erp-nfce-regularizacao.js';
    $regJsVersion = @filemtime(public_path($regJs)) ?: \App\Support\Erp\ErpAssetVersion::bundle();
@endphp
<div class="erp-nfce-regularizacao-host">
    <script src="{{ asset($regJs) }}?v={{ $regJsVersion }}" defer data-navigate-track></script>

    @if ($this->nfceRegularizacaoOpen)
        <livewire:erp.nfce-regularizacao wire:key="erp-nfce-regularizacao" />
    @endif
</div>
