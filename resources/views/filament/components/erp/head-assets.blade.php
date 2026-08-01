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
        launchUrl: '/admin/erp-update/launch',
        downloadUrl: '/admin/erp-update/download',
        statusUrl: '/admin/erp-update/status',
        resetUrl: '/admin/erp-update/reset',
        assetVersion: @json($version),
        appVersion: @json(config('unitec.versao')),
        zipName: @json(config('unitec.update_zip_name', 'Unitec-ERP-Update.zip')),
        stallSeconds: 600,
        downloadStallSeconds: 600,
        extractingStallSeconds: 900,
        backingUpStallSeconds: 600,
        applyingStallSeconds: 600,
        migratingStallSeconds: 600,
        finalizingStallSeconds: 600,
        startingStallSeconds: 600,
        maxMinutes: 90,
    };
</script>
<meta name="erp-asset-version" content="{{ $version }}-{{ config('unitec.versao') }}">
@include('filament.components.erp.no-browser-hints')
<script src="{{ asset('js/erp-compras.js') }}?v={{ $version }}" defer></script>
@if (ErpPageAssets::resourceSegment() === 'nfe')
    <script src="{{ asset('js/erp-nfe-lancamento.js') }}?v={{ $version }}" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'notas-fornecedores')
    <script src="{{ asset('js/erp-notas-fornecedores.js') }}?v={{ $version }}" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'products')
    <script src="{{ asset('js/erp-precif-enter-v5.js') }}?v={{ $version }}"></script>
@endif

@if (ErpPageAssets::routeKind() === 'dashboard')
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}?v={{ $version }}"></script>
    <script src="{{ asset('js/erp-home-charts.js') }}?v={{ $version }}" defer></script>
@endif
