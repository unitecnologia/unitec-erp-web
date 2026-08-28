@php
    use App\Support\Erp\ErpAssetVersion;
    use App\Support\Erp\ErpPageAssets;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
@endphp

<meta name="erp-asset-version" content="{{ $version }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}">
@include('filament.components.erp.no-browser-hints')
<script src="{{ asset('js/erp-compras.js') }}?v={{ $version }}" defer></script>
@if (ErpPageAssets::resourceSegment() === 'compras')
    <script src="{{ asset('js/erp-compras-lanc-enter.js') }}?v={{ $version }}-v9"></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'contas-receber')
    <script src="{{ asset('js/erp-receber-form-enter.js') }}?v={{ $version }}"></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'nfe')
    <script src="{{ asset('js/erp-nfe-lancamento.js') }}?v={{ $version }}" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'notas-fornecedores')
    <script src="{{ asset('js/erp-notas-fornecedores.js') }}?v={{ $version }}" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'products' || ErpPageAssets::resourceSegment() === 'compras')
    <script src="{{ asset('js/erp-precif-enter-v5.js') }}?v={{ $version }}-v37-single-request"></script>
@endif

@if (ErpPageAssets::routeKind() === 'dashboard')
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}?v={{ $version }}"></script>
    <script src="{{ asset('js/erp-home-charts.js') }}?v={{ $version }}" defer></script>
    <script src="{{ asset('js/erp-warm-prefetch.js') }}?v={{ $version }}" defer></script>
@endif
