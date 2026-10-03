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
    <script src="{{ asset('js/erp-compras-parcelas.js') }}?v={{ $version }}-v5"></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'contas-receber')
    <script src="{{ asset('js/erp-receber-form-enter.js') }}?v={{ $version }}"></script>
    <script src="{{ asset('js/erp-receber-boleto-venc-progress.js') }}?v={{ $version }}-v2"></script>
    <script src="{{ asset('js/erp-boleto-print.js') }}?v={{ $version }}-v2"></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'nfe')
    <script src="{{ asset('js/erp-nfe-lancamento.js') }}?v={{ $version }}" defer></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'nfse')
    <script src="{{ asset('js/erp-nfse-lancamento.js') }}?v={{ $version }}" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'notas-fornecedores')
    <script src="{{ asset('js/erp-notas-fornecedores.js') }}?v={{ $version }}-v2" defer></script>
@endif
@if (ErpPageAssets::resourceSegment() === 'permissoes')
    <script src="{{ asset('js/erp-permissoes.js') }}?v={{ $version }}-v1" defer></script>
@endif

@if (ErpPageAssets::resourceSegment() === 'products' || ErpPageAssets::resourceSegment() === 'compras')
    <script src="{{ asset('js/erp-precif-enter-v5.js') }}?v={{ $version }}-v38-local-enter"></script>
@endif

@if (ErpPageAssets::routeKind() === 'dashboard' && \App\Support\Erp\ErpAccess::currentCan('dashboard.access'))
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}?v={{ $version }}"></script>
    <script src="{{ asset('js/erp-home-charts.js') }}?v={{ $version }}" defer></script>
    <script src="{{ asset('js/erp-warm-prefetch.js') }}?v={{ $version }}" defer></script>
@endif
