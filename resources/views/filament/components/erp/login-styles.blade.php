@php
    use App\Support\Erp\ErpAssetVersion;

    $version = ErpAssetVersion::bundle();
@endphp

<link rel="stylesheet" href="{{ asset('css/erp-login.css') }}?v={{ $version }}">
