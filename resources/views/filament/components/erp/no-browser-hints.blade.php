@php
    use App\Support\Erp\ErpAssetVersion;

    $version = ErpAssetVersion::bundle();
@endphp

<script src="{{ asset('js/erp-no-browser-hints.js') }}?v={{ $version }}-{{ config('unitec.versao') }}"></script>
