@php
    use App\Support\Erp\ErpAssetVersion;

    if (! filament()->auth()->check()) {
        return;
    }

    $version = ErpAssetVersion::bundle();
@endphp

@php
    $shellJsPath = public_path('js/erp-shell.js');
    $pdvJsPath = public_path('js/erp-pdv.js');
    $shellJsVersion = $version.'-shell'.(is_file($shellJsPath) ? (int) filemtime($shellJsPath) : time());
    $pdvJsVersion = $version.'-pdv'.(is_file($pdvJsPath) ? (int) filemtime($pdvJsPath) : time());
@endphp
<script src="{{ asset('js/erp-shell.js') }}?v={{ $shellJsVersion }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}"></script>
{{-- PDV no layout: menu Filament usa SPA e o script da tela PDV não reexecuta. --}}
<script src="{{ asset('js/erp-pdv.js') }}?v={{ $pdvJsVersion }}" defer></script>
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
