@php
    use App\Support\Erp\ErpAssetVersion;

    $version = ErpAssetVersion::bundle();
    $hintsPath = public_path('js/erp-no-browser-hints.js');
    $hintsV = is_file($hintsPath) ? (int) filemtime($hintsPath) : time();
@endphp

<script src="{{ asset('js/erp-no-browser-hints.js') }}?v={{ $version }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}-v5-{{ $hintsV }}"></script>
