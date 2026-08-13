@php
    use App\Support\Erp\ErpAssetVersion;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
@endphp

<script src="{{ asset('js/erp-shell.js') }}?v={{ $version }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}"></script>
<script>
    window.ErpDeviceConfig = {
        baseUrl: @json(config('unitec.device_service.base_url', 'http://127.0.0.1:9330')),
        apiKey: @json(config('unitec.device_service.api_key', '')),
        timeoutMs: {{ (int) config('unitec.device_service.timeout_ms', 2500) }},
        ensureUrl: @json(route('erp.device-service.ensure'))
    };
</script>
<script src="{{ asset('js/erp-device-service.js') }}?v={{ $version }}"></script>
<script src="{{ asset('js/erp-silent-print.js') }}?v={{ $version }}"></script>
@include('filament.components.erp.form-scripts')
@if (request()->is('admin/orcamentos*'))
    <script src="{{ asset('js/erp-orcamentos.js') }}?v={{ $version }}"></script>
@endif
