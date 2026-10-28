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
    $pdvLoaderPath = public_path('js/erp-pdv-loader.js');
    $shellJsVersion = $version.'-shell'.(is_file($shellJsPath) ? (int) filemtime($shellJsPath) : time());
    $pdvJsVersion = $version.'-pdv'.(is_file($pdvJsPath) ? (int) filemtime($pdvJsPath) : time());
    $pdvLoaderVersion = $version.'-pdvldr'.(is_file($pdvLoaderPath) ? (int) filemtime($pdvLoaderPath) : time());
    $silentPrintJsPath = public_path('js/erp-silent-print.js');
    $silentPrintJsVersion = $version.'-sp'.(is_file($silentPrintJsPath) ? (int) filemtime($silentPrintJsPath) : time());
    $pdvScriptUrl = asset('js/erp-pdv.js').'?v='.$pdvJsVersion;
@endphp
<script src="{{ asset('js/erp-shell.js') }}?v={{ $shellJsVersion }}-{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}"></script>
{{-- PDV sob demanda: SPA não reexecuta script da tela; loader carrega/teardown ao entrar/sair. --}}
<script>
    window.__erpPdvScriptUrl = @json($pdvScriptUrl);
</script>
<script src="{{ asset('js/erp-pdv-loader.js') }}?v={{ $pdvLoaderVersion }}" defer></script>
<script>
    window.ErpDeviceConfig = {
        baseUrl: @json(config('unitec.device_service.base_url', 'http://127.0.0.1:9330')),
        apiKey: @json(config('unitec.device_service.api_key', '')),
        timeoutMs: {{ (int) config('unitec.device_service.timeout_ms', 2500) }},
        ensureUrl: @json(route('erp.device-service.ensure'))
    };
</script>
<script src="{{ asset('js/erp-device-service.js') }}?v={{ $version }}"></script>
<script src="{{ asset('js/erp-silent-print.js') }}?v={{ $silentPrintJsVersion }}"></script>
@include('filament.components.erp.form-scripts')
@if (request()->is('admin/orcamentos*'))
    <script src="{{ asset('js/erp-orcamentos.js') }}?v={{ $version }}"></script>
@endif
