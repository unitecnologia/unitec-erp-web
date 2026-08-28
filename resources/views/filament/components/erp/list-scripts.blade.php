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
<script src="{{ asset('js/erp-uppercase.js') }}?v={{ $jsVersion }}" defer data-navigate-track></script>
<script src="{{ asset('js/erp-list.js') }}?v={{ $jsVersion }}" defer data-navigate-track></script>
