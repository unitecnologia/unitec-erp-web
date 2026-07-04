@php
    use App\Support\Erp\ErpAssetVersion;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
@endphp

<script src="{{ asset('js/erp-shell.js') }}?v={{ $version }}-{{ config('unitec.versao') }}"></script>
@include('filament.components.erp.form-scripts')
@if (request()->is('admin/orcamentos*'))
    <script src="{{ asset('js/erp-orcamentos.js') }}?v={{ $version }}"></script>
@endif
