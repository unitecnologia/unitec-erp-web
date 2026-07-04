@once('erp-form-scripts-styles')
@php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
@endphp

<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}?v={{ $version }}">
<link rel="stylesheet" href="{{ asset('css/erp-datepicker.css') }}?v={{ $version }}">
@endonce
