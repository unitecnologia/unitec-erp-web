@php
    use App\Support\Erp\ErpAssetVersion;

    $jsVersion = ErpAssetVersion::bundle();
@endphp

<script>
    window.__erpListConfigs = window.__erpListConfigs || [];
    window.__erpListConfigs.push(@json($config));
</script>
<script src="{{ asset('js/erp-uppercase.js') }}?v={{ $jsVersion }}" defer></script>
<script src="{{ asset('js/erp-list.js') }}?v={{ $jsVersion }}" defer></script>