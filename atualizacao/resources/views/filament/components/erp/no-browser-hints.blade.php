@php
    use App\Support\Erp\ErpAssetVersion;

    $version = ErpAssetVersion::bundle();
@endphp

<script src="{{ asset('js/erp-no-browser-hints.js') }}?v={{ $version }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}-v3-global"></script>
