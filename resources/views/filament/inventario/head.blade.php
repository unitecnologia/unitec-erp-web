@php
    $inventarioPwaVersion = '16';
@endphp
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#0f6b4c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Inventário">
<link rel="stylesheet" href="{{ asset('css/erp-inventario.css') }}?v={{ $inventarioPwaVersion }}">
<link rel="icon" href="{{ asset('pwa-inventario/icons/favicon.png') }}?v={{ $inventarioPwaVersion }}" type="image/png" sizes="32x32">
<link rel="manifest" href="{{ asset('manifest-inventario.webmanifest') }}?v={{ $inventarioPwaVersion }}">
<link rel="apple-touch-icon" href="{{ asset('pwa-inventario/icons/apple-touch-icon.png') }}?v={{ $inventarioPwaVersion }}" sizes="180x180">
<title>Unitec Inventário</title>
<script>
    window.__unitecInventarioBip = window.__unitecInventarioBip || null;
    window.addEventListener('beforeinstallprompt', function (event) {
        try { event.preventDefault(); } catch (e) {}
        window.__unitecInventarioBip = event;
    });
    window.UnitecInventarioInstall = function () {
        var event = window.__unitecInventarioBip;
        if (!event) {
            alert('Para instalar, use o menu do navegador: Adicionar à tela inicial.');
            return;
        }
        event.prompt();
    };
</script>
<script src="{{ asset('js/gestor-scan.js') }}?v={{ $inventarioPwaVersion }}" defer></script>
<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var swUrl = @json(asset('sw-inventario.js'));
        swUrl += (swUrl.indexOf('?') >= 0 ? '&' : '?') + 'v={{ $inventarioPwaVersion }}';

        window.addEventListener('load', function () {
            if (!window.isSecureContext && !/^(localhost|127\.0\.0\.1|::1)$/i.test(location.hostname || '')) {
                return;
            }

            navigator.serviceWorker.register(swUrl, {
                scope: '/inventario/',
                updateViaCache: 'none',
            }).catch(function () {});
        });
    })();
</script>
