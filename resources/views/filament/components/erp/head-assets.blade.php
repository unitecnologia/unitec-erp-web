@php
    use App\Support\Erp\ErpAssetVersion;
    use App\Support\Erp\ErpPageAssets;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
@endphp

<script>
    window.__erpUpdateConfig = {
        launchUrl: @json(route('erp.update.launch')),
        statusUrl: @json(route('erp.update.status')),
        resetUrl: @json(route('erp.update.reset')),
        assetVersion: @json($version),
        appVersion: @json(config('unitec.versao')),
        zipName: @json(config('unitec.update_zip_name', 'Unitec-ERP-Update.zip')),
        stallSeconds: 180,
        downloadStallSeconds: 900,
        applyingStallSeconds: 600,
        maxMinutes: 45,
    };
</script>
<meta name="erp-asset-version" content="{{ $version }}-{{ config('unitec.versao') }}">
<script src="{{ asset('js/erp-compras.js') }}?v={{ $version }}" defer></script>

@if (ErpPageAssets::routeKind() === 'dashboard')
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}?v={{ $version }}"></script>
    <script src="{{ asset('js/erp-home-charts.js') }}?v={{ $version }}" defer></script>
@endif
