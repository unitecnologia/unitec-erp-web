@php
    use App\Support\Erp\ErpAssetVersion;

    $jsVersion = ErpAssetVersion::bundle();
@endphp

<script data-navigate-track>
    window.__erpListConfigs = window.__erpListConfigs || [];
    window.__erpListConfigs.push(@json($config));

    if (typeof window.registerErpListConfig === 'function') {
        window.registerErpListConfig(@json($config));
    } else if (typeof window.initErpListPages === 'function') {
        window.initErpListPages();
    }
</script>
{{-- erp-uppercase.js já vem do shell via form-scripts; não recarregar (evita hooks Livewire duplicados). --}}
<script src="{{ asset('js/erp-list.js') }}?v={{ $jsVersion }}" defer data-navigate-track></script>
@include('filament.components.erp.grid-sort-script')
